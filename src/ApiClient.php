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
    private $timeout = 120;

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
            'item_name'  => $this->plugin->name(),
            'slug'       => $this->plugin->slug(),
            'version'    => $this->plugin->version(),
            'license'    => $this->plugin->licenseKey(),
        ], $requestData);

        try {
            $response = wp_remote_post($this->plugin->updateServerUrl(), [
                'timeout'   => $this->timeout,
                'sslverify' => false,
                'body'      => $requestData,
            ]);
        } catch (\Exception $e) {
            return $this->handleExceptionResponse($e);
        }

        if (is_wp_error($response)) {
            return $this->handleWPErrorResponse($response);
        }

        if ($this->isError($response)) {
            return $this->handleErrorResponse($response['response']['code'], $response['response']['message'], $response['body']);
        }

        return $this->buildResponseObject($response);

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

    public function getLatestVersion($beta = false)
    {
        return $this->call('get_version', ['beta' => $beta]);
    }

    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
    }

    protected function handleWPErrorResponse(WP_Error $error)
    {
        $errors = [];

        foreach ($error->errors as $key => $message) {
            $errors[] = $key.': '.$message[0];
        }

        return $this->handleErrorResponse(500, implode("\n", $errors));
    }

    protected function isError($response)
    {
        if (isset($response['response'])) {
            if (isset($response['response']['code'])) {
                return $response['response']['code'] >= 400;
            }
        }

        return false;
    }

    protected function handleErrorResponse($code, $message = null, $body = null)
    {
        return (new Response())
            ->withCode($code)
            ->withMessage($message)
            ->withBody($body);
    }

    protected function handleExceptionResponse(\Exception $e)
    {
        throw $e;
    }

    protected function buildResponseObject($response)
    {
        return (new Response())
            ->withCode($response['response']['code'])
            ->withMessage($response['response']['message'])
            ->withVersion($this->buildVersionObject($response));
    }

    protected function buildVersionObject($response)
    {
        $version = new Version(json_decode(wp_remote_retrieve_body($response)));

        if ($version && isset($version->sections)) {
            $version->sections = maybe_unserialize($version->sections);
        } else {
            $version = false;
        }
        if ($version && isset($version->banners)) {
            $version->banners = maybe_unserialize($version->banners);
        }

        if (!empty($version->sections)) {
            foreach ($version->sections as $key => $section) {
                $version->$key = (array) $section;
            }
        }

        return $version;
    }
}
