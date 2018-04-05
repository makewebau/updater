<?php

namespace MakeWeb\Updater;

use WP_Error;

class ApiClient
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    private $api_url = '';
    private $api_data = [];
    private $name = '';
    private $slug = '';
    private $version = '';
    private $wp_override = false;
    private $cache_key = '';

    /**
     * Disable SSL verification in order to prevent download update failures.
     *
     * @param array  $args
     * @param string $url
     *
     * @return object $array
     */
    public function http_request_args($args, $url)
    {
        // If it is an https request and we are performing a package download, disable ssl verification
        if (strpos($url, 'https://') !== false && strpos($url, 'edd_action=package_download')) {
            $args['sslverify'] = false;
        }

        return $args;
    }

    /**
     * Calls the API and, if successfull, returns the object delivered by the API.
     *
     * @uses get_bloginfo()
     * @uses wp_remote_post()
     * @uses is_wp_error()
     *
     * @param string $action The requested action.
     * @param array  $data   Parameters for the API action.
     *
     * @return false|object
     */
    public function call($action = 'get_version', $requestData = [])
    {
        global $wp_version;

        if ($this->plugin->updateServerUrl() == trailingslashit(home_url())) {
            throw new \Exception('Plugin server must be another server');
        }

        $requestData = array_merge([
            'edd_action' => $action,
            'item_name' => $this->plugin->name(),
            'slug' => $this->plugin->slug(),
            'version' => $this->plugin->version(),
            'license' => $this->plugin->licenseKey(),
        ], $requestData);

        $response = wp_remote_post($this->plugin->updateServerUrl(), [
            'timeout' => 120,
            'sslverify' => false,
            'body' => $requestData,
        ]);

        if (is_wp_error($response)) {
            return $this->handleErrorResponse($response);
        }

        $response = json_decode(wp_remote_retrieve_body($response));

        if ($response && isset($response->sections)) {
            $response->sections = maybe_unserialize($response->sections);
        } else {
            $response = false;
        }

        if ($response && isset($response->banners)) {
            $response->banners = maybe_unserialize($response->banners);
        }

        if (!empty($response->sections)) {
            foreach ($response->sections as $key => $section) {
                $response->$key = (array) $section;
            }
        }

        return $response;
    }

    public function getPluginInfo()
    {
        return $this->call('plugin_information', [
            'slug' => $this->plugin->slug(),
            'is_ssl' => is_ssl(),
            'fields' => [
                'banners' => [],
                'reviews' => false,
            ],
        ]);
    }

    public function getLatestVersion($beta = false)
    {
        return $this->call('get_version', ['beta' => $beta]);
    }

    protected function handleErrorResponse(WP_Error $error)
    {
        $errors = [];

        foreach ($error->errors as $key => $message) {
            $errors[] = $key.': '.$message[0];
        }

        throw new \Exception(
            implode($errors, "\n")
        );
    }
}
