<?php

namespace App\Providers;

use App\Support\Queue\Connectors\RabbitMQConnector;
use Illuminate\Queue\QueueServiceProvider as ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function registerConnectors($manager)
    {
        parent::registerConnectors($manager);
        $this->registerRabbitmqConnector($manager);
    }

    /**
     * Register the RabbitMq queue connector.
     *
     * @param  \Illuminate\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerRabbitmqConnector($manager)
    {
        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector();
        });
    }
}
