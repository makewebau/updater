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

        // Replace action for what we display plugin update notification under the plugin row
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

        $versionInfo = $this->getCachedVersionInfo();

        if ($versionInfo === false) {
            $versionInfo = $this->apiClient->getLatestVersion();
            $this->setVersionInfoCache($versionInfo);
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

    public function getCachedVersionInfo($cacheKey = '')
    {
        if (empty($cacheKey)) {
            $cacheKey = $this->getCacheKey();
        }

        $cache = get_option($cacheKey);

        // If cache is expired return early
        if (empty($cache['timeout']) || current_time('timestamp') > $cache['timeout']) {
            return false;
        }

        return json_decode($cache['value']);
    }

    protected function setVersionInfoCache($value = '', $cacheKey = '')
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
        $eddApiRequestTransient = $this->getCachedVersionInfo($cacheKey);

        // If we have no transient-saved value, fetch one from the API, set a fresh transient with the API value,
        // and return that value too right now.
        if (empty($eddApiRequestTransient)) {
            $eddApiRequestTransient = $this->apiClient->getPluginInfo();

            // Expires in 3 hours
            $this->setVersionInfoCache($response, $eddApiRequestTransient);
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
     * show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
     *
     * @param string $file
     * @param array  $plugin
     */
    public function showUpdateNotification($file, $plugin)
    {
        if (is_network_admin()) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            return;
        }

        if (is_multisite()) {
            return;
        }

        if ($this->plugin->basename() != $file) {
            return;
        }

        // Remove our filter on the site transient
        remove_filter('pre_set_site_transient_update_plugins', [$this, 'checkIfUpdateIsAvailable'], 10);

        $update_cache = get_site_transient('update_plugins');

        $update_cache = is_object($update_cache) ? $update_cache : new stdClass();

        if (empty($update_cache->response) || empty($update_cache->response[$this->plugin->basename()])) {
            $versionInfo = $this->getCachedVersionInfo();

            if (false === $versionInfo) {
                $versionInfo = $this->api_request('plugin_latest_version', ['slug' => $this->plugin->slug(), 'beta' => $this->beta]);

                $this->setVersionInfoCache($versionInfo);
            }

            if (!is_object($versionInfo)) {
                return;
            }

            if (version_compare($this->plugin->version(), $versionInfo->new_version, '<')) {
                $update_cache->response[$this->plugin->basename()] = $versionInfo;
            }

            $update_cache->last_checked = current_time('timestamp');
            $update_cache->checked[$this->plugin->basename()] = $this->plugin->version();

            set_site_transient('update_plugins', $update_cache);
        } else {
            $versionInfo = $update_cache->response[$this->plugin->basename()];
        }

        // Restore our filter
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkIfUpdateIsAvailable']);

        if (!empty($update_cache->response[$this->plugin->basename()]) && version_compare($this->plugin->version(), $versionInfo->new_version, '<')) {

            // build a plugin list row, with update notification
            $wp_list_table = _get_list_table('WP_Plugins_List_Table');
            // <tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
            echo '<tr class="plugin-update-tr" id="'.$this->plugin->slug().'-update" data-slug="'.$this->plugin->slug().'" data-plugin="'.$this->plugin->slug().'/'.$file.'">';
            echo '<td colspan="3" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt">';

            $changelog_link = self_admin_url('index.php?edd_sl_action=view_plugin_changelog&plugin='.$this->plugin->basename().'&slug='.$this->plugin->slug().'&TB_iframe=true&width=772&height=911');

            if (empty($versionInfo->download_link)) {
                printf(
                    __('There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'easy-digital-downloads'),
                    esc_html($versionInfo->name),
                    '<a target="_blank" class="thickbox" href="'.esc_url($changelog_link).'">',
                    esc_html($versionInfo->new_version),
                    '</a>'
                );
            } else {
                printf(
                    __('There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'easy-digital-downloads'),
                    esc_html($versionInfo->name),
                    '<a target="_blank" class="thickbox" href="'.esc_url($changelog_link).'">',
                    esc_html($versionInfo->new_version),
                    '</a>',
                    '<a href="'.esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=').$this->plugin->basename(), 'upgrade-plugin_'.$this->plugin->basename())).'">',
                    '</a>'
                );
            }

            do_action("in_plugin_update_message-{$file}", $plugin, $versionInfo);

            echo '</div></td></tr>';
        }
    }

    public function showChangelog()
    {
        global $edd_plugin_data;

        if (empty($_REQUEST['edd_sl_action']) || 'view_plugin_changelog' != $_REQUEST['edd_sl_action']) {
            return;
        }

        if (empty($_REQUEST['plugin'])) {
            return;
        }

        if (empty($_REQUEST['slug'])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have permission to install plugin updates', 'easy-digital-downloads'), __('Error', 'easy-digital-downloads'), ['response' => 403]);
        }

        $data = $edd_plugin_data[$_REQUEST['slug']];
        $beta = !empty($data['beta']) ? true : false;
        $cache_key = md5('edd_plugin_'.sanitize_key($_REQUEST['plugin']).'_'.$beta.'_version_info');
        $versionInfo = $this->getCachedVersionInfo($cache_key);

        if (false === $versionInfo) {
            $api_params = [
                'edd_action' => 'get_version',
                'item_name'  => isset($data['item_name']) ? $data['item_name'] : false,
                'item_id'    => isset($data['item_id']) ? $data['item_id'] : false,
                'author'     => isset($data['author']) ? $data['author'] : false,
                'slug'       => $_REQUEST['slug'],
                'url'        => home_url(),
                'beta'       => !empty($data['beta']),
            ];

            $request = wp_remote_post($this->api_url, ['timeout' => 15, 'sslverify' => false, 'body' => $api_params]);

            if (!is_wp_error($request)) {
                $versionInfo = json_decode(wp_remote_retrieve_body($request));
            }

            if (!empty($versionInfo) && isset($versionInfo->sections)) {
                $versionInfo->sections = maybe_unserialize($versionInfo->sections);
            } else {
                $versionInfo = false;
            }

            if (!empty($versionInfo)) {
                foreach ($versionInfo->sections as $key => $section) {
                    $versionInfo->$key = (array) $section;
                }
            }

            $this->setVersionInfoCache($versionInfo, $cache_key);
        }

        if (!empty($versionInfo) && isset($versionInfo->sections->changelog)) {
            echo '<div style="background:#fff;padding:10px;">'.$versionInfo->sections->changelog.'</div>';
        }

        exit;
    }

    protected function getCacheKey()
    {
        $cacheKey = md5(serialize($this->plugin->slug().$this->plugin->licenseKey().$this->beta));
    }

    protected function displayMessage($message)
    {
        require __DIR__.'/views/error.php';
    }
}
