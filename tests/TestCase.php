<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Declares a global function with the given name which returns the given callback.
     *
     * @var string
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
}
