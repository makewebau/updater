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
    public function call($action, $data)
    {
        global $wp_version;

        $data = array_merge($this->api_data, $data);

        if ($this->plugin->updateServerUrl() == trailingslashit(home_url())) {
            throw new \Exception('Plugin server must be another server');
        }

        $requestData = [
            'edd_action' => 'get_version',
            'license'    => $this->plugin->licenseKey(),
            'item_name'  => $this->plugin->basename(),
            'item_id'    => false,
            'version'    => $this->plugin->version(),
            'slug'       => $this->plugin->slug(),
            'author'     => $this->plugin->vendorName(),
            'url'        => home_url(),
            'beta'       => false,
        ];

        $response = wp_remote_post($this->plugin->updateServerUrl(), [
            'timeout'   => 15,
            'sslverify' => false,
            'body'      => $requestData,
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
            'slug'   => $this->plugin->slug(),
            'is_ssl' => is_ssl(),
            'fields' => [
                'banners' => [],
                'reviews' => false,
            ],
        ]);
    }

    public function getLatestVersion()
    {
        return $this->call('get_version');
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
