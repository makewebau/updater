<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Declares a global function with the given name which returns the given callback
     *
     * @var $functionName string
     * @var callback $callback
     */
    protected function setGlobalFunctionCallback($functionName, $callback)
    {
        global $globalFunctionCallbacks;

        if (!function_exists($functionName)) {
            eval("function $functionName( ...\$args) {
                global \$globalFunctionCallbacks;
                return \$globalFunctionCallbacks['$functionName'](...\$args);
            }");
        }

        $globalFunctionCallbacks[$functionName] = $callback;
    }

    public function wp_remote_post($url, $args = [])
    {
        $http = _wp_http_get_object();

        return $http->post($url, $args);
    }

    public static function setUpBeforeClass()
    {
        self::startTestServer();
    }

    public static function startTestServer()
    {
        if (getenv('TEST_SERVER_URL')) {
            return;
        }

        $server = new class {
            public static function start()
            {
                $url = getenv('TEST_SERVER_URL') ?: '127.0.0.1:'.getenv('TEST_SERVER_PORT');
                $pid = exec('php -S '.$url.' -t ./tests/server/public > /dev/null 2>&1 & echo $!');
                while (@file_get_contents("http://$url/get") === false) {
                    usleep(1000);
                }
                register_shutdown_function(function () use ($pid) {
                    exec('kill '.$pid);
                });
            }
        };

        $server::start();
    }
}
