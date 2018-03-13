<?php

namespace App\Support\Queue;

use App\Support\Queue\Jobs\RabbitMQJob;
use DateTime;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQQueue extends Queue implements QueueContract
{
    /**
     * Used for retry logic, to set the retries on the message metadata instead of the message body.
     */
    const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $connection;
    protected $channel;

    protected $declareExchange;
    protected $declaredExchanges = [];

    protected $declareBindQueue;
    protected $declaredQueues = [];

    protected $defaultQueue;
    protected $configQueue = [
        'passive' => false,
        'durable' => false,
        'exclusive' => false,
        'auto_delete' => true,
    ];
    protected $configExchange = [
        'name' => '',
        'type' => 'direct',
        'passive' => false,
        'durable' => false,
        'auto_delete' => true,
    ];

    /**
     * @var int
     */
    private $attempts;

    /**
     * @var string
     */
    private $correlationId;

    /**
     * @param AMQPStreamConnection $amqpConnection
     * @param array $config
     */
    public function __construct(AMQPStreamConnection $amqpConnection, $config)
    {
        $this->connection = $amqpConnection;
        $this->defaultQueue = $config['queue'] ?? '';
        $this->configQueue = array_merge($this->configQueue, $config['queue_params'] ?? []);
        $this->configExchange = array_merge($this->configExchange, $config['exchange_params'] ?? []) ;
        $this->declareExchange = $config['exchange_declare'] ?? false;
        $this->declareBindQueue = $config['queue_declare_bind'] ?? false;

        $this->channel = $this->getChannel();
    }

    /**
     * Get the size of the queue.
     *
     * @param  string $queue
     * @return int
     */
    public function size($queue = null)
    {
        return $this->channel->queue_declare($this->getQueueName($queue));
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return bool
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, []);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  array $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queue = $this->getQueueName($queue);
        $this->declareQueue($queue);
        if (isset($options['delay']) && $options['delay'] > 0) {
            list($queue, $exchange) = $this->declareDelayedQueue($queue, $options['delay']);
        } else {
            list($queue, $exchange) = $this->declareQueue($queue);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'delivery_mode' => 2,
        ];

        if (isset($this->attempts) === true) {
            $headers['application_headers'] = [self::ATTEMPT_COUNT_HEADERS_KEY => ['I', $this->attempts]];
        }

        // push job to a queue
        $message = new AMQPMessage($payload, $headers);

        $correlationId = $this->getCorrelationId();
        $message->set('correlation_id', $correlationId);

        // push task to a queue
        $this->channel->basic_publish($message, $exchange, $queue);

        return $correlationId;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, ['delay' => $this->getSeconds($delay)]);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     *
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueueName($queue);

        // declare queue if not exists
        $this->declareQueue($queue);

        // get envelope
        $message = $this->channel->basic_get($queue);

        if ($message instanceof AMQPMessage) {
            return new RabbitMQJob($this->container, $this, $this->channel, $queue, $message);
        }
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    private function getQueueName($queue)
    {
        return $queue ?: $this->defaultQueue;
    }

    /**
     * @return AMQPChannel
     */
    private function getChannel()
    {
        return $this->connection->channel();
    }

    /**
     * @param $name
     *
     * @return array
     */
    private function declareQueue($name)
    {
        $name = $this->getQueueName($name);
        $exchange = $this->configExchange['name'] ?: $name;

        if ($this->declareExchange && !in_array($exchange, $this->declaredExchanges, true)) {
            // declare exchange
            $this->channel->exchange_declare(
                $exchange,
                $this->configExchange['type'],
                $this->configExchange['passive'],
                $this->configExchange['durable'],
                $this->configExchange['auto_delete']
            );

            $this->declaredExchanges[] = $exchange;
        }

        if ($this->declareBindQueue && !in_array($name, $this->declaredQueues, true)) {
            // declare queue
            $this->channel->queue_declare(
                $name,
                $this->configQueue['passive'],
                $this->configQueue['durable'],
                $this->configQueue['exclusive'],
                $this->configQueue['auto_delete']
            );

            // bind queue to the exchange
            $this->channel->queue_bind($name, $exchange, $name);

            $this->declaredQueues[] = $name;
        }

        return [$name, $exchange];
    }

    /**
     * @param string $destination
     * @param DateTime|int $delay
     *
     * @return string
     */
    private function declareDelayedQueue($destination, $delay)
    {
        $delay = $this->getSeconds($delay);
        $destination = $this->getQueueName($destination);
        $destinationExchange = $this->configExchange['name'] ?: $destination;
        $name = $this->getQueueName($destination) . '_deferred_' . $delay;
        $exchange = $this->configExchange['name'] ?: $destination;

        // declare exchange
        if (!in_array($exchange, $this->declaredExchanges, true)) {
            $this->channel->exchange_declare(
                $exchange,
                $this->configExchange['type'],
                $this->configExchange['passive'],
                $this->configExchange['durable'],
                $this->configExchange['auto_delete']
            );
        }

        // declare queue
        if (!in_array($name, $this->declaredQueues, true)) {
            $this->channel->queue_declare(
                $name,
                $this->configQueue['passive'],
                $this->configQueue['durable'],
                $this->configQueue['exclusive'],
                $this->configQueue['auto_delete'],
                false,
                new AMQPTable([
                    'x-dead-letter-exchange' => $destinationExchange,
                    'x-dead-letter-routing-key' => $destination,
                    'x-message-ttl' => $delay * 1000,
                ])
            );
        }

        // bind queue to the exchange
        $this->channel->queue_bind($name, $exchange, $name);

        return [$name, $exchange];
    }

    /**
     * Sets the attempts member variable to be used in message generation.
     *
     * @param int $count
     *
     * @return void
     */
    public function setAttempts($count)
    {
        $this->attempts = $count;
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId($id)
    {
        $this->correlationId = $id;
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId()
    {
        return $this->correlationId ?: uniqid('', false);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
