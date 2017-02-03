<?php

require_once __DIR__.'/../../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Evaneos\Burrow\BurrowServiceProvider(), [
    'evaneos_burrow.options.drivers' => [
        'default' => [
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'rabbitmq',
            'pwd' => 'rabbitmq'
        ]
    ],
    'evaneos_burrow.options.publishers' => [
        'default' => [
            'driver' => 'default',
            'exchange' => 'some_exchange'
        ]
    ],
]);

$app->get('/publish/{message}', function ($message) use ($app) {
    $app['evaneos_burrow.publishers']['default']->publish($message);
});

$app->run();