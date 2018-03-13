<?php

namespace App\Providers;

use App\Support\Broadcasting\Broadcasters\RabbitMQBroadcaster;
use App\Support\Queue\Connectors\RabbitMQConnector;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\BroadcastServiceProvider as ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * @inheritdoc
     */
    public function boot()
    {
//        $broadcaster = $this->app->make('Illuminate\Contracts\Broadcasting\Factory');
//        $broadcaster->routes();
//        $broadcaster->channel('App.User.*', function ($user, $userId) {
//            return (int) $user->id === (int) $userId;
//        });
    }

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    public function register()
    {
        parent::register();

        $this->app->extend('Illuminate\Broadcasting\BroadcastManager', function (BroadcastManager $manager) {
            return $manager->extend('rabbitmq', function ($manager, array $config) {
                $connector = new RabbitMQConnector();
                return new RabbitMQBroadcaster($connector->connect($config));
            });
        });
    }
}
