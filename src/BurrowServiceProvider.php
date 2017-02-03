<?php

namespace Evaneos\Burrow;

use Pimple\ServiceProviderInterface;
use Pimple\Container;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Assert\Assertion;
use Burrow\Driver\DriverFactory;
use Burrow\Publisher\AsyncPublisher;
use Evaneos\Daemon\CLI\DaemonWorkerCommand;
use Burrow\Handler\HandlerBuilder;
use Burrow\QueueConsumer;
use Burrow\Daemon\QueueHandlingDaemon;
use Evaneos\Daemon\Worker;

class BurrowServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function register(Container $container)
    {
        $container['evaneos_burrow.drivers'] = function ($container) {
            $drivers = new Container();
            foreach ($container['evaneos_burrow.options.drivers'] as $name => $values) {
                $drivers[$name] = function () use ($values) {
                    return DriverFactory::getDriver([
                        'host' => $values['host'],
                        'port' => $values['port'],
                        'user' => $values['user'],
                        'pwd'  => $values['pwd'],
                    ]);
                };
            }

            return $drivers;
        };

        $container['evaneos_burrow.publishers'] = function ($container) {
            $publishers = new Container();
            foreach ($container['evaneos_burrow.options.publishers'] as $name => $values) {
                $publishers[$name] = function () use ($container, $values) {

                    Assertion::true(isset($container['evaneos_burrow.drivers'][$values['driver']]));

                    $driver = $container['evaneos_burrow.drivers'][$values['driver']];

                    return new AsyncPublisher($driver, $values['exchange']);
                };
            }

            return $publishers;
        };

        $container['evaneos_burrow.workers'] = function ($container) {
            $workers = new Container();
            foreach ($container['evaneos_burrow.options.workers'] as $name => $values) {
                $workers[$name] = function () use ($container, $values) {

                    Assertion::true(isset($container['evaneos_burrow.drivers'][$values['driver']]));

                    $driver = $container['evaneos_burrow.drivers'][$values['driver']];
                    $handlerBuilder = new HandlerBuilder($driver);

                    Assertion::true(isset($container[$values['consumer']]));
                    Assertion::isInstanceOf($container[$values['consumer']], QueueConsumer::class);

                    $handler = $handlerBuilder->async()->build($container[$values['consumer']]);
                    $daemon = new QueueHandlingDaemon($driver, $handler, $values['queue']);

                    return new Worker($daemon);
                };
            }

            return $workers;
        };
    }

    public function boot(Application $app)
    {
        if (class_exists(\Knp\Console\ConsoleEvents::class)) {
            $app['dispatcher']->addListener(\Knp\Console\ConsoleEvents::INIT, function(\Knp\Console\ConsoleEvent $event) use ($app) {
                foreach ($app['evaneos_burrow.options.workers'] as $name => $values) {
                    $event->getApplication()->add(new DaemonWorkerCommand($app['evaneos_burrow.workers'][$name], sprintf('evaneos_burrow:consume:%s', $name)));
                }
            });
        }
    }
}