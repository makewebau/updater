<?php

require __DIR__.'/../vendor/autoload.php';

(new MakeWeb\WordpressTestEnvironment\WordpressTestEnvironment)
    ->withEnvPath(__DIR__.'/..')
    ->boot();
