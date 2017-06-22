<?php

namespace MakeWeb\Updater;

class Updater
{
    protected $pluginFilePath;

    public function __construct($pluginFilePath)
    {
        $this->pluginFilePath = $pluginFilePath;
    }

    public function boot()
    {
        $this->plugin = new Plugin($this->pluginFilePath);

        (new RegistersPluginSettings($this->plugin))->boot();
        (new HandlesActivatingPlugins($this->plugin))->boot();
        (new HandlesUpdatingPlugins($this->plugin))->boot();
    }
}
