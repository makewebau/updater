<?php

namespace MakeWeb\Updater;

class HandlesUpdatingPlugins
{
    protected $plugin;

    protected $beta;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;

        // We'll hard code this to false now and implement it later as needed
        $this->beta = false;

        $this->apiClient = new ApiClient($plugin);
    }

    public function boot()
    {
        add_action('admin_init', function () {
            global $edd_plugin_data;

            $this->api_url = trailingslashit($this->plugin->updateServerUrl());

            $this->api_data = [
                'version'   => $this->plugin->version(),
                'license'   => $this->plugin->licenseKey(),
                'item_name' => $this->plugin->name(),
            ];

            $edd_plugin_data[$this->plugin->slug()] = $this->api_data;
        });

        // Set up hooks.
        add_filter('plugins_api', [$this, 'pluginsApiFilter'], 10, 3);
        add_action('admin_init', [$this, 'showChangelog']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkIfUpdateIsAvailable']);

        // Replace action for plugin update notification under the plugin row
        add_action('load-plugins.php', function () {
            $pluginBasename = $this->plugin->basename();

            remove_action("after_plugin_row_$pluginBasename", 'wp_plugin_update_row', 10);
            add_action("after_plugin_row_$pluginBasename", [$this, 'showUpdateNotification'], 10, 2);
        }, 25);
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @uses api_request()
     *
     * @param array $transientData Update array build by WordPress.
     *
     * @return array Modified update array with custom plugin data.
     */
    public function checkIfUpdateIsAvailable($transientData)
    {
        if (!is_object($transientData)) {
            $transientData = new \stdClass();
        }

        global $pagenow;

        if ('plugins.php' == $pagenow && is_multisite()) {
            return $transientData;
        }

        if (!empty($transientData->response) && !empty($transientData->response[$this->plugin->name()])) {
            return $transientData;
        }

        // If the plugin has already been checked
        if (isset($transientData->checked)) {
            if (isset($transientData->checked[$this->plugin->name()])) {
                return $transientData;
            }
        }

        $versionInfo = $this->getVersionInfoFromCache();

        if ($versionInfo === false) {
            $versionInfo = $this->apiClient->getLatestVersion();
            $this->cacheVersionInfo($versionInfo);
        }

        if (isset($versionInfo->msg)) {
            $this->displayMessage($versionInfo->msg);
        }

        if (false !== $versionInfo && is_object($versionInfo) && isset($versionInfo->new_version)) {
            if (version_compare($this->plugin->version(), $versionInfo->new_version, '<')) {
                $transientData->response[$this->plugin->basename()] = $versionInfo;
            }

            $transientData->last_checked = current_time('timestamp');
            $transientData->checked[$this->plugin->basename()] = $this->plugin->version();
        }

        return $transientData;
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
    public function pluginsApiFilter($data, $action = '', $args = null)
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
     * Displays update information for the plugin.
     *
     * @param string $file        Plugin basename.
     * @param array  $plugin_data Plugin information.
     *
     * @return mixed
     *
     * @see wp_plugin_update_row
     */
    public function showUpdateNotification($file, $plugin_data)
    {
        if (!$update_info = $this->getUpdateInfo()) {
            return false;
        }

        $response = $update_info->response[$this->plugin->basename()];

        if (!version_compare($this->plugin->version(), $response->new_version, '<')) {
            return false;
        }

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

            echo '<tr class="plugin-update-tr'.$active_class.'" id="'.esc_attr($response->slug.'-update').'" data-slug="'.esc_attr($response->slug).'" data-plugin="'.esc_attr($file).'"><td colspan="'.esc_attr($wp_list_table->get_column_count()).'" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>';

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
                    wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=').$file, 'upgrade-plugin_'.$file),
                    sprintf(
                        'class="update-link" aria-label="%s"',
                        /* translators: %s: plugin name */
                        esc_attr(sprintf(__('Update %s now'), $plugin_name))
                    )
                );
            }

            do_action("in_plugin_update_message-{$file}", $plugin_data, $response);

            echo '</p></div></td></tr>';
        }
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
     * Return plugin update data from either the site transient or the remote API.
     *
     * @return object|null
     */
    protected function getUpdateInfo()
    {
        if ($cachedUpdateInfo = $this->getUpdateInfoFromCache()) {
            return $cachedUpdateInfo;
        }

        return $this->getUpdateInfoFromApi();
    }

    /**
     * Return plugin update info from the local update cache, if available.
     *
     * @return object|null
     */
    protected function getUpdateInfoFromCache()
    {
        $updateCache = get_site_transient('update_plugins');

        return empty($updateCache->response[$this->plugin->basename()])
            ? null
            : $updateCache;
    }

    /**
     * Return plugin update info from the remote API.
     *
     * @return object|null
     */
    protected function getUpdateInfoFromApi()
    {
        if ($versionInfo = $this->getVersionInfo()) {
            $this->cacheUpdateInfo($updateInfo = $this->buildUpdateInfoByVersion($versionInfo));

            return $updateInfo;
        }
    }

    /**
     * Sets local plugins update cache.
     *
     * By first unhooking the "checkIfUpdateIsAvailable" and then rehooking it, so that we won't be hitting the remote
     * API along the way.
     *
     * @param object|null $updateCache
     *
     * @return void
     */
    protected function cacheUpdateInfo($updateCache)
    {
        remove_filter('pre_set_site_transient_update_plugins', $updateHook = [$this, 'checkIfUpdateIsAvailable']);

        set_site_transient('update_plugins', $updateCache);

        add_filter('pre_set_site_transient_update_plugins', $updateHook);
    }

    /**
     * Builds update info object out of the given version info object.
     *
     * @param object $versionInfo
     *
     * @return null|object
     */
    protected function buildUpdateInfoByVersion($versionInfo)
    {
        if (!is_object($versionInfo)) {
            return;
        }

        $basename = $this->plugin->basename();

        return (object) [
            'last_checked' => current_time('timestamp'),
            'checked'      => [$basename => $this->plugin->version()],
            'response'     => version_compare($this->plugin->version(), $versionInfo->new_version, '<')
                ? [$basename => $versionInfo]
                : [],
        ];
    }

    /**
     * Returns version info either from the cache or from the remote API.
     *
     * @return object|null
     */
    protected function getVersionInfo()
    {
        if ($versionInfo = $this->getVersionInfoFromCache()) {
            return $versionInfo;
        }

        return $this->getVersionInfoFromApi();
    }

    /**
     * Returns version info from the local cache.
     *
     * @param string|null $cacheKey
     *
     * @return null|object
     */
    protected function getVersionInfoFromCache($cacheKey = null)
    {
        $cacheKey === null and $cacheKey = $this->getCacheKey();

        $cache = get_option($cacheKey);

        // If cache is expired return early
        if (empty($cache['timeout']) || current_time('timestamp') > $cache['timeout']) {
            return;
        }

        return json_decode($cache['value']);
    }

    /**
     * Returns version info from the remote API. Also, updates the local cache.
     *
     * @return object|null
     */
    protected function getVersionInfoFromApi()
    {
        $versionInfo = $this->apiClient->call('get_version', [
            'slug' => $this->plugin->slug(),
            'beta' => $this->beta,
        ]);

        // @todo: Check for failures.

        $this->cacheVersionInfo($versionInfo);

        return $versionInfo;
    }

    /**
     * Updates version info in the local cache.
     *
     * @param string $value
     * @param string $cacheKey
     *
     * @return void
     */
    protected function cacheVersionInfo($value = '', $cacheKey = '')
    {
        if (empty($cacheKey)) {
            $cacheKey = $this->getCacheKey();
        }

        update_option($cacheKey, [
            'timeout' => strtotime('+3 hours', current_time('timestamp')),
            'value'   => json_encode($value),
        ]);
    }

    /**
     * Generates plugin's MD5 hash.
     *
     * @return string
     */
    protected function getCacheKey()
    {
        return md5(serialize($this->plugin->slug().$this->plugin->licenseKey().$this->beta));
    }

    protected function displayMessage($message)
    {
        require __DIR__.'/views/error.php';
    }
}
