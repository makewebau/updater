<?php

namespace Tests\Unit;

use MakeWeb\Updater\HandlesUpdatingPlugins;
use MakeWeb\Updater\Plugin;
use Tests\TestCase;

class HandlesUpdatingPluginsTest extends TestCase
{
    /** @test */
    public function get_latest_version_test_returns_expected_result_when_update_is_available()
    {
        $handler = new class(new Plugin(realpath(__DIR__.'/../test-plugin/test-plugin.php'))) extends HandlesUpdatingPlugins {
            public function callGetLatestVersion()
            {
                return $this->getLatestVersion();
            }
        };

        $this->assertEquals('1.2.3', $handler->callGetLatestVersion()->new_version);
    }
}
