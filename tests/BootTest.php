<?php

namespace MakeWeb\Updater\Tests;

use WP_Mock;

class BootTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        WP_Mock::setUp();
    }

    public function tearDown()
    {
        WP_Mock::tearDown();
    }

    /** @test */
    public function the_updater_client_class_can_be_booted()
    {
        WP_Mock::expectActionAdded('admin_init', \Mockery::any());

        $updater = (new \MakeWeb\Updater\Client)
            ->setPluginFilePath(__FILE__)
            ->setUpdateServerUrl('http://optimusdivi.com')
            ->boot();

    }
}
