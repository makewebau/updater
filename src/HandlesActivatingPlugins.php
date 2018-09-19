<?php

namespace MakeWeb\Updater;

class HandlesActivatingPlugins
{
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function boot()
    {
        add_action('admin_init', [$this, 'handleActivationButtonSubmission']);
    }

    public function handleActivationButtonSubmission()
    {
        // If the activation button was not clicked we can return early
        if (!isset($_POST[$this->plugin->licenseKeyFieldName()]) && !isset($_POST['edd_license_key_action'])) {
            return;
        }

        // If the nonce key does not match we know there's something fishy afoot
        if (false == check_admin_referer($this->plugin->activationActionName(), $this->plugin->activationNonceKey())) {
            return;
        }

        if ($_POST['edd_license_key_action'] == 'activate') {
            return $this->handleActivation();
        }

        if ($_POST['edd_license_key_action'] == 'deactivate') {
            return $this->handleDeactivation();
        }

        $this->redirectWithErrorMessage('Invalid license key action: '.$_POST['license_key_action']);
    }

    protected function responseIsError($response)
    {
        if (is_wp_error($response)) {
            return true;
        }

        return wp_remote_retrieve_response_code($response) !== 200;
    }

    protected function handleErrorResponse($response)
    {
        if (is_wp_error($response)) {
            $this->redirectWithErrorMessage($response->get_error_message());
        }
        $this->redirectWithErrorMessage(__('An error occurred while trying to activate'.$this->plugin->name().', please try again.'));
    }

    protected function redirectWithErrorMessage($message)
    {
        wp_redirect(add_query_arg(
            [
            'sl_activation' => 'false',
            'message'       => urlencode($message),
        ],
            $this->plugin->licensePageUrl()
        ));

        exit();
    }

    protected function handleUnsuccessfulActivation($licenseData)
    {
        if ($licenseData->error == 'expired') {
            $this->redirectWithErrorMessage(
                sprintf(
                    __('Your license key expired on %s.'),
                    date_i18n(get_option('date_format'), strtotime($licenseData->expires, current_time('timestamp')))
                )
            );
        }

        if ($licenseData->error == 'revoked') {
            $this->redirectWithErrorMessage(__('Your license key for '.$this->plugin->name().' has been disabled.'));
        }

        if ($licenseData->error == 'missing') {
            $this->redirectWithErrorMessage(__('Invalid license key for '.$this->plugin->name()));
        }

        if ($licenseData->error == 'invalid' || $licenseData->error == 'site_inactive') {
            $this->redirectWithErrorMessage(__('Your license key for '.$this->plugin->name().' is not active for this URL.'));
        }

        if ($licenseData->error == 'item_name_mismatch') {
            $this->redirectWithErrorMessage(sprintf(__('This appears to be an invalid license key for %s.'), $this->plugin->name()));
        }

        if ($licenseData->error == 'no_activations_left') {
            $this->redirectWithErrorMessage(__('Your license key for '.$this->plugin->name().' has reached its activation limit.'));
        }

        $this->redirectWithErrorMessage('An error occurred while activating '.$this->plugin->name().', please try again.');
    }

    public function checkIfLicenseIsStillValid()
    {
        global $wp_version;

        $license = trim(get_option('edd_sample_license_key'));

        $api_params = [
            'edd_action' => 'check_license',
            'license'    => $license,
            'item_name'  => urlencode($this->getPluginName()),
            'url'        => home_url(),
        ];

        // Call the custom API.
        $response = wp_remote_post($this->updateServerUrl, ['timeout' => 15, 'sslverify' => false, 'body' => $api_params]);

        if (is_wp_error($response)) {
            return false;
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        if ($license_data->license == 'valid') {
            echo 'valid';
            exit;
        // this license is still valid
        } else {
            echo 'invalid';
            exit;
            // this license is no longer valid
        }
    }

    protected function newLicenseKey()
    {
        return trim($_POST[$this->plugin->licenseKeyFieldName()]);
    }

    protected function handleActivation()
    {
        $this->plugin->saveLicenseKey($this->newLicenseKey());

        $licenseData = $this->callApi($this->newLicenseKey(), 'activate_license');

        // If we got to here the license activation must have been successful
        // The value of $licenseData->license will be either "valid" or "invalid"
        $this->plugin->setLicenseStatus($licenseData->license);

        $this->redirect($this->plugin->licensePageUrl());
    }

    protected function handleDeactivation()
    {
        $licenseData = $this->callApi($this->plugin->licenseKey(), 'deactivate_license');

        $this->plugin->deleteLicenseStatus();

        $this->redirect($this->plugin->licensePageUrl());
    }

    protected function redirect($url)
    {
        wp_redirect($url);

        exit();
    }

    /**
     *  Call the API on the update server.
     **/
    protected function callApi($licenseKey, $action)
    {
        $response = wp_remote_post(
            $this->plugin->updateServerUrl(),
            [
                'timeout'   => 60,
                'sslverify' => false,
                'body'      => [
                    'edd_action' => $action,
                    'license'    => $licenseKey,
                    'item_name'  => urlencode($this->plugin->name()),
                    'url'        => home_url(),
                ],
            ]
        );

        // If we received an error we should handle it
        if ($this->responseIsError($response)) {
            $this->handleErrorResponse($response);
        }

        // Get the license data from the response
        $licenseData = json_decode(wp_remote_retrieve_body($response));

        // If the license activation was not successful
        if ($licenseData->success === false) {
            $this->handleUnsuccessfulActivation($licenseData);
        }

        return $licenseData;
    }
}
