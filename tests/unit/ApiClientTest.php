<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use MakeWeb\Updater\ApiClient;
use MakeWeb\Updater\Plugin;
use MakeWeb\Updater\Response;
use MakeWeb\Updater\Version;
use Tests\TestCase;

class ApiClientTest extends TestCase
{
    /** @test */
    public function get_latest_version_test()
    {
        $response = (new ApiClient(
            new Plugin(realpath(__DIR__.'/../test-plugin/test-plugin.php'))
        ))->getLatestVersion();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertInstanceOf(Version::class, $response->version);

        foreach ([
            'new_version' => '1.2.3',
            'stable_version' => '1.2.3',
            'name' => 'Test Plugin',
            'slug' => 'test-plugin',
        ] as $key => $value) {
            $this->assertEquals($value, $response->version->$key);
        }
    }

    /** @test */
    public function get_latest_version_returns_appropriate_error_message_when_no_such_plugin_exists()
    {
        $response = (new ApiClient(
            new Plugin(realpath(__DIR__.'/../test-plugin/no-such-plugin.php'))
        ))->getLatestVersion();

        $this->assertFalse($response->version->new_version);
        $this->assertFalse($response->version->stable_version);
        $this->assertEquals('no-such-plugin', $response->version->slug);
    }

    /** @test */
    public function get_latest_version_call_gracefully_handles_500_response()
    {
        $response = (new ApiClient(
            new Plugin(realpath(__DIR__.'/../test-plugin/500-response-plugin.php'))
        ))->getLatestVersion();
        $this->assertTrue($response->isError());
        $this->assertEquals('500', $response->code);
        $this->assertNotEquals('http_request_failed: A valid URL was not provided.', $response->message);
    }

    /** @test */
    public function get_latest_version_call_gracefully_handles_timeout()
    {
        $apiClient = (new ApiClient(
            new Plugin(realpath(__DIR__.'/../test-plugin/timeout-request-plugin.php'))
        ));

        $apiClient->setTimeout(1);
        $response = $apiClient->getLatestVersion();

        $this->assertTrue($response->isError());
        $this->assertEquals('500', $response->code);
        $this->assertTrue(
            Str::contains($response->message, 'http_request_failed: cURL error 28: Operation timed out after')
        );
        global $kill_test_server;

        @$kill_test_server();
    }
}
