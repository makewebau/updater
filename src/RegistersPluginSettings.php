<?php

namespace MakeWeb\Updater;

class RegistersPluginSettings
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function boot()
    {
        add_action('admin_menu', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            $this->registerAdminMenu();
        });

        add_action('admin_init', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            $this->registerSettings();
            $this->registerSettingsFields();
        });
    }

    public function registerAdminMenu()
    {
        // If the license page has already been registered we should stop here
        if ($this->plugin->licensePageAlreadyRegistered()) {
            return;
        }

        add_plugins_page(
            $this->plugin->licensePageTitle(),
            $this->plugin->licensePageTitle(),
            'manage_options',
            $this->plugin->licensePageSlug(),
            [$this, 'displayLicensePage']
        );

        add_action('admin_notices', [$this, 'displayNotices']);
    }

    public function registerSettingsFields()
    {
        // Add the license fields for the plugin
        add_filter($this->plugin->licenseFieldsHook(), [$this, 'displayLicenseField']);
    }

    public function registerSettings()
    {
        // creates our settings in the options table
        register_setting($this->plugin->optionsGroupName(), $this->plugin->licenseKeyFieldName(), [$this, 'sanitizeLicenseKey']);
    }

    public function displayLicensePage()
    {
        $plugin = $this->plugin;

        require __DIR__.'/views/license-page.php';
    }

    public function displayLicenseField($existingFields)
    {
        $plugin = $this->plugin;

        require __DIR__.'/views/license-field.php';
    }

    public function displayNotices()
    {
        // If there is nothing to display, return early
        if (!isset($_GET['sl_activation']) || empty($_GET['message'])) {
            return;
        }

        if ($_GET['sl_activation'] == 'false') {
            return $this->displayMessage(urldecode($_GET['message']));
        }
    }

    protected function displayMessage($message)
    {
        require __DIR__.'/views/error.php';
    }
}
