<?php

namespace Tests\Unit;

use MakeWeb\Updater\ApiClient;
use MakeWeb\Updater\Plugin;
use Tests\TestCase;

class ApiClientTest extends TestCase
{
    /** @test */
    public function get_latest_version_test()
    {
        $this->setGlobalFunctionCallback('wp_remote_retrieve_body', function ($response) {
            return '';
        });

        $this->setGlobalFunctionCallback('is_wp_error', function ($error) {
            return false;
        });

        $this->setGlobalFunctionCallback('wp_remote_post', function ($url, $params) {
            $this->assertTrue($params['timeout'] == 120, $params['timeout']);

            return [];
        });

        $this->setGlobalFunctionCallback('get_option', function ($key) {
            global $testOptions;

            return $testOptions[$key];
        });

        $this->setGlobalFunctionCallback('get_option', function ($key) {
            global $testOptions;

            return $testOptions[$key];
        });

        $this->setGlobalFunctionCallback('home_url', function () {
            return 'domain.com';
        });

        $this->setGlobalFunctionCallback('get_file_data', function () {
            return [
                'AuthorURI' => 'localhost',
                'Name'      => 'Plugin Name',
                'Version'   => '1.2.3',
            ];
        });

        $this->setGlobalFunctionCallback('trailingslashit', function ($input) {
            return $input;
        });

        (new ApiClient(
            new Plugin(__DIR__.'/../test-plugin.php')
        ))->getLatestVersion();
    }
}
