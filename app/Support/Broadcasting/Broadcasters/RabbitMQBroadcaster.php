<?php

namespace App\Support\Broadcasting\Broadcasters;

use App\Support\Queue\RabbitMQQueue;
use Illuminate\Contracts\Broadcasting\Broadcaster;

class RabbitMQBroadcaster implements Broadcaster
{
    /**
     * The Redis instance.
     *
     * @var \App\Support\Queue\RabbitMQQueue
     */
    protected $queue;

    /**
     * Create a new broadcaster instance.
     *
     * @param \App\Support\Queue\RabbitMQQueue $queue
     */
    public function __construct(RabbitMQQueue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $payload = json_encode(['event' => $event, 'data' => $payload]);

        foreach ($channels as $channel) {
            $this->queue->pushRaw($payload, $channel);
        }
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function auth($request)
    {
        return;
    }

    /**
     * Return the valid authentication response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  mixed $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        return;
    }
}
