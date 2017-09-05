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
        add_filter('plugins_api', [$this, 'hookPluginsApi'], 10, 3);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'hookUpdatePluginsTransient']);

        // Allow connecting to locally hosted plugin servers if in debug mode
        WP_DEBUG and add_filter('http_request_host_is_external', '__return_true');
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
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     *
     * @return object
     */
    public function hookPluginsApi($result, $action = '', $args = null)
    {
        return $action === 'plugin_information' && isset($args->slug) && $args->slug === $this->plugin->slug()
            ? $this->getPluginInfo()
            : $result;
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
        $basename = $this->plugin->basename();

        return empty($transient->response[$basename])
            ? null
            : $transient->response[$basename];
    }

    /**
     * Return a plugin info object to be used in as the source for "View version x.y.z details" pages.
     *
     * Returning theupdate object from the transient cache will do. However, one can customize the rendering of the
     * details page by manipulating the update object within this method.
     *
     * Here's a list of accepted keys to customize the plugin info page:
     * name, slug, version, requires, tested, rating, upgrade_notice, num_ratings, downloaded, active_installs,
     * homepage, last_updated, author, sections, banners
     *
     * @return object
     */
    protected function getPluginInfo()
    {
        $update = $this->getUpdateFromTransient();

        $update->author = $this->plugin->formattedVendorName();

        return $update;
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
        $basename = $this->plugin->basename();

        return isset($transient->checked[$basename]) && !empty($transient->response[$basename]);
    }

    /**
     * Displays the passed error message and exits.
     *
     * @param string $message
     *
     * @return void
     *
     * @todo Re-implement in WP way of showing errors.
     */
    protected function error($message)
    {
        require __DIR__.'/views/error.php';
    }
}
