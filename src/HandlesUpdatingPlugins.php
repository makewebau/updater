<?php

namespace MakeWeb\Updater;

class HandlesUpdatingPlugins
{
    /**
     * Host plugin instance.
     *
     * @var Plugin
     */
    protected $plugin;

    /**
     * Beta indicator flag.
     *
     * @var bool
     */
    protected $beta = false;

    /**
     * HandlesUpdatingPlugins constructor.
     *
     * @param Plugin $plugin
     *
     * @todo Break out the update checking functionality into its own class.
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;

        $this->apiClient = new ApiClient($plugin);
    }

    /**
     * Installs required hooks for plugin update operations.
     *
     * @return void
     */
    public function boot()
    {
        add_action('admin_init', [$this, 'showChangelog']);
        add_filter('plugins_api', [$this, 'hookPluginsApi'], 10, 3);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'hookUpdatePluginsTransient']);

        // Replace action for plugin update notification under the plugin row
        add_action('load-plugins.php', function () {
            $basename = $this->plugin->basename();

            remove_action("after_plugin_row_$basename", 'wp_plugin_update_row', 10);
            add_action("after_plugin_row_$basename", [$this, 'hookAfterPluginRow'], 10, 2);
        }, 25);
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * Dives into the update API just when WordPress creates its update array, then adds a custom API call
     * and injects the custom plugin update information retrieved from the API into the update transient.
     *
     * @param object $transient The "update_plugins" transient object built by WP.
     *
     * @return object
     */
    public function hookUpdatePluginsTransient($transient)
    {
        if ($this->updateIsInjected($transient)) {
            return $transient;
        }

        if ($latestVersion = $this->getLatestVersion() and isset($latestVersion->msg)) {
            $this->error($latestVersion->msg);
        }

        return $this->injectUpdate($transient, $latestVersion);
    }

    /**
     * Updates information on the "View version x.x details" page with custom data.
     *
     * @uses api_request()
     *
     * @param mixed  $data
     * @param string $action
     * @param object $args
     *
     * @return object $data
     */
    public function hookPluginsApi($data, $action = '', $args = null)
    {
        if ($action != 'plugin_information') {
            return $data;
        }

        if (!isset($args->slug) || ($args->slug != $this->plugin->slug())) {
            return $data;
        }

        $cacheKey = 'edd_api_request_'.md5(serialize($this->plugin->slug().$this->plugin->licenseKey().$this->beta));

        // Get the transient where we store the api request for this plugin for 24 hours
        $eddApiRequestTransient = $this->getVersionInfoFromCache($cacheKey);

        // If we have no transient-saved value, fetch one from the API, set a fresh transient with the API value,
        // and return that value too right now.
        if (empty($eddApiRequestTransient)) {
            $eddApiRequestTransient = $this->apiClient->getPluginInfo();

            // Expires in 3 hours
            $this->cacheVersionInfo($response, $eddApiRequestTransient);
        }

        // Convert sections into an associative array, since we're getting an object, but Core expects an array.
        if (isset($eddApiRequestTransient->sections) && !is_array($eddApiRequestTransient->sections)) {
            $newSections = [];

            foreach ($eddApiRequestTransient->sections as $key => $value) {
                $newSections[$key] = $value;
            }

            $data->sections = $newSections;
        }

        // Convert banners into an associative array, since we're getting an object, but Core expects an array.
        if (isset($data->banners) && !is_array($data->banners)) {
            $new_banners = [];
            foreach ($data->banners as $key => $value) {
                $new_banners[$key] = $value;
            }

            $data->banners = $new_banners;
        }

        return $data;
    }

    /**
     * Displays update information for the plugin under its row in the plugins listing.
     *
     * @param string $file        Plugin basename.
     * @param array  $pluginData  Plugin information array from the updates transient.
     *
     * @return mixed
     */
    public function hookAfterPluginRow($file, $pluginData)
    {
        $latestVersion = $this->getUpdateFromTransient();

        return $this->updateIsAvailable($latestVersion)
            ? $this->printUpdateNotification($file, $pluginData, $latestVersion)
            : false;
    }

    /**
     * Shows plugin changelog.
     *
     * @return void
     *
     * @todo Hook into the organic way of showing plugin changelogs.
     */
    public function showChangelog()
    {
        if (empty($_REQUEST['edd_sl_action']) || $_REQUEST['edd_sl_action'] !== 'view_plugin_changelog') {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have permission to install plugin updates', 'easy-digital-downloads'), __('Error', 'easy-digital-downloads'), ['response' => 403]);
        }

        if ($versionInfo = $this->getVersionInfo()) {
            echo '<div style="background:#fff;padding:10px;">',
                (empty($versionInfo->sections->changelog) ? 'Could not fetch the changelog.' : $versionInfo->sections->changelog),
            '</div>', die();
        }
    }

    /**
     * Alters the update transient object with the new version information, only if necessary.
     *
     * @param object $transient
     * @param object $latestVersion
     *
     * @return object
     */
    protected function injectUpdate($transient, $latestVersion)
    {
        if ($this->updateIsAvailable($latestVersion)) {
            $transient->response[$this->plugin->basename()] = $latestVersion;
        }

        return $this->markPluginAsChecked($transient);
    }

    /**
     * Checks whether a new version is available by probing the given remote version object.
     *
     * @param object|null $latestVersion
     *
     * @return bool
     */
    protected function updateIsAvailable($latestVersion = null)
    {
        $latestVersion or $latestVersion = $this->getUpdateFromTransient();

        return isset($latestVersion->new_version) &&
            version_compare($this->plugin->version(), $latestVersion->new_version, '<');
    }

    /**
     * Marks plugin as "checked" in the update transient object. Also touches the timestamp.
     *
     * @param object $transient
     *
     * @return object
     */
    protected function markPluginAsChecked($transient)
    {
        $transient->checked[$this->plugin->basename()] = $this->plugin->version();

        $transient->last_checked = current_time('timestamp');

        return $transient;
    }

    /**
     * Return plugin update info from cached plugin update transient, if available.
     *
     * @return object|null
     */
    protected function getUpdateFromTransient()
    {
        $transient = get_site_transient('update_plugins');
        $basename  = $this->plugin->basename();

        return empty($transient->response[$basename])
            ? null
            : $transient->response[$basename];
    }

    /**
     * Returns plugin's latest version info from the remote API.
     *
     * @return object|null
     */
    protected function getLatestVersion()
    {
        return $this->apiClient->getLatestVersion($this->beta);
    }

    /**
     * Checks whether the update transient contains the plugin update info or not.
     *
     * @param object $transient
     *
     * @return bool
     */
    protected function updateIsInjected($transient)
    {
        $pluginName = $this->plugin->name();

        return isset($transient->checked[$pluginName]) && !empty($transient->response[$pluginName]);
    }

    /**
     * Displays the passed error message and exits.
     *
     * @param string $message
     *
     * @return void
     * @todo Reimplement in WP way of showing errors.
     */
    protected function error($message)
    {
        require __DIR__.'/views/error.php';
    }

    /**
     * Shows update notification after the plugin row in the admin plugins listing.
     *
     * It's a fork of WP's core `wp_plugin_update_row()` enabling us to customize the update notification.
     *
     * @param string $file        Plugin basename.
     * @param array  $plugin_data Plugin information.
     * @param object $response    Latest version object from the update transient cache.
     *
     * @return mixed
     *
     * @see wp_plugin_update_row
     */
    protected function printUpdateNotification($file, $plugin_data, $response)
    {
        $plugin_name = $this->plugin->filteredName();
        $details_url = self_admin_url("index.php?edd_sl_action=view_plugin_changelog&plugin=$file&slug={$response->slug}&TB_iframe=true&width=600&height=800");

        $wp_list_table = _get_list_table('WP_Plugins_List_Table');

        if (is_network_admin() || !is_multisite()) {
            // This nested duplicate condition here is right from the latest WP core. Just a note :)
            if (is_network_admin()) {
                $active_class = is_plugin_active_for_network($file) ? ' active' : '';
            } else {
                $active_class = is_plugin_active($file) ? ' active' : '';
            }

            echo '<tr class="plugin-update-tr' . $active_class . '" id="' . esc_attr($response->slug . '-update') . '" data-slug="' . esc_attr($response->slug) . '" data-plugin="' . esc_attr($file) . '"><td colspan="' . esc_attr($wp_list_table->get_column_count()) . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>';

            if (!current_user_can('update_plugins')) {
                /* translators: 1: plugin name, 2: details URL, 3: additional link attributes, 4: version number */
                printf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.'),
                    $plugin_name,
                    esc_url($details_url),
                    sprintf(
                        'class="thickbox open-plugin-details-modal" aria-label="%s"',
                        /* translators: 1: plugin name, 2: version number */
                        esc_attr(sprintf(__('View %1$s version %2$s details'), $plugin_name, $response->new_version))
                    ),
                    esc_html($response->new_version)
                );
            } elseif (empty($response->package)) {
                /* translators: 1: plugin name, 2: details URL, 3: additional link attributes, 4: version number */
                printf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this plugin.</em>'),
                    $plugin_name,
                    esc_url($details_url),
                    sprintf(
                        'class="thickbox open-plugin-details-modal" aria-label="%s"',
                        /* translators: 1: plugin name, 2: version number */
                        esc_attr(sprintf(__('View %1$s version %2$s details'), $plugin_name, $response->new_version))
                    ),
                    esc_html($response->new_version)
                );
            } else {
                /* translators: 1: plugin name, 2: details URL, 3: additional link attributes, 4: version number, 5: update URL, 6: additional link attributes */
                printf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.'),
                    $plugin_name,
                    esc_url($details_url),
                    sprintf(
                        'class="thickbox open-plugin-details-modal" aria-label="%s"',
                        /* translators: 1: plugin name, 2: version number */
                        esc_attr(sprintf(__('View %1$s version %2$s details'), $plugin_name, $response->new_version))
                    ),
                    esc_html($response->new_version),
                    wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file),
                    sprintf(
                        'class="update-link" aria-label="%s"',
                        /* translators: %s: plugin name */
                        esc_attr(sprintf(__('Update %s now'), $plugin_name))
                    )
                );
            }

            do_action("in_plugin_update_message-$file", $plugin_data, $response);

            echo '</p></div></td></tr>';
        }
    }
}
