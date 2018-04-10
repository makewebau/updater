<?php

require_once __DIR__.'/../../../vendor/autoload.php';

$app = new Laravel\Lumen\Application(
    realpath(__DIR__.'/../')
);

function build_response($response = [], $code = null)
{
    $code = $code ?? 200;

    return response()->json($response, $code);
}

// Test plugin
$testPluginResponse = function () {
    return build_response([
        'new_version'    => '1.2.3',
        'sections'       => '',
        'stable_version' => '1.2.3',
        'name'           => 'Test Plugin',
        'slug'           => 'test-plugin',
    ]);
};
$app->router->get('test-plugin', $testPluginResponse);
$app->router->post('test-plugin', $testPluginResponse);

// No such plugin
$testPluginResponse = function () {
    return build_response([
        'new_version'    => false,
        'sections'       => '',
        'stable_version' => false,
        'slug'           => 'no-such-plugin',
    ]);
};

$app->router->get('no-such-plugin', $testPluginResponse);
$app->router->post('no-such-plugin', $testPluginResponse);

$app->router->get('500', function () {
    return build_response(['message' => 'Error'], 500);
});
$app->router->post('500', function () {
    return build_response(['message' => 'Error'], 500);
});

$app->router->get('timeout', function () {
    sleep(99999);
});
$app->router->post('timeout', function () {
    sleep(99999);
});

$app->router->get('/get', function () {
    return build_response(app('request'));
});

$app->router->get('/', function () {
    return build_response(['version' => '1.2.3']);
});

// $app->router->post('/', function () {
//     return build_response(['version' => '1.2.3']);
// });

$app->run();
