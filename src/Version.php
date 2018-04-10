<?php

namespace MakeWeb\Updater;

class Version extends \stdClass
{
    public $new_version;

    public $stable_version;

    public function __construct($parameters = [])
    {
        foreach ($parameters as $key => $value) {
            $this->$key = $value;
        }
    }
}
