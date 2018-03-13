# Connector for RabbitMq to Laravel App

add in composer `"php-amqplib/php-amqplib": "2.6.*"`

main files in 
- `Support/Broadcasting`
- `Support/Queue`

and
- `BroadcastServiceProvider.php`
- `QueueServiceProvider.php`


## For broadcast to rabbit

in `config/broadcasting.php` add new connection (names _office_events_ and _office.events_ just examples)

```
'connections' => [

        'office_events' => [
            'driver' => env('EVENTS_BROADCAST_DRIVER', 'log'),
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'login' => env('RABBITMQ_LOGIN', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'exchange_params' => [
                'name' => 'office.events',
                'type' => 'fanout'
            ]
        ],

        //...
]
```

And in Event Listeners subscriber - `ForServicesBroadcaster` (add it in `EventServiceProvider` to `$subscribe` array)
Listen events and broadcast it by `Illuminate\Broadcasting\BroadcastManager`

## Helper command for listen rabbit queue

Add connection in `config/queue.php` 

exchange_params.name (office.events) same as exchange_params.name from `config/broadcasting`

```
    'connections' => [
        'office_events_reading' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'login' => env('RABBITMQ_LOGIN', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'queue_declare_bind' => true,
            'queue_params' => ['exclusive' => true],
            'exchange_params' => ['name' => 'office.events']
        ],
        
        //...
    ],

```

And add simple command with code

```
while (true) {
    $msg = app('queue')->connection('office_events_reading')->pop();
    if (null !== $msg) {
        $this->line(" [x] {$msg->getRawBody()}");
        $msg->delete();
    }
}
```

(see ListenOfficeEventsBroadcastingCommand.php)
