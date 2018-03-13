<?php

namespace App\Listeners;

use App\Models;
use App\Events\SomeEvent;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Events\Dispatcher;

class ForServicesBroadcaster extends BaseEventSubscriber
{
    /**
     * @var \Illuminate\Broadcasting\BroadcastManager
     */
    protected $manager;

    /**
     * @param BroadcastManager $manager
     */
    public function boot(BroadcastManager $manager)
    {
        $this->manager = $manager;
    }

    public function subscribe(Dispatcher $dispatcher)
    {
        $this->subscribeEloquentEvent($dispatcher, Model\SomeModel::class, 'deleted', 'broadcastDeleted');
        $this->subscribeEloquentEvent($dispatcher, Model\SomeModel::class, 'updated', 'broadcastUpdated');
        $this->subscribeEloquentEvent($dispatcher, Model\SomeModel::class, 'created', 'broadcastCreated');
        $dispatcher->listen(SomeEvent::class, $this->handler('onSomeEvent'));
    }

    public function onSomeEvent(SomeEvent $event)
    {
        $payload = [
            'foo' => $event->getFoo(),
            'bar' => $event->getBar(),
        ];
        $this->broadcast('some', $payload);
    }

    public function broadcastCreated($model)
    {
        $this->broadcastEloquentEvent('created', $model);
    }

    public function broadcastDeleted($model)
    {
        $this->broadcastEloquentEvent('deleted', $model);
    }

    public function broadcastUpdated($model)
    {
        $this->broadcastEloquentEvent('updated', $model);
    }

    protected function broadcastEloquentEvent($name, $model)
    {
        $this->broadcast(snake_case(class_basename($model)) . '.' . $name, $model->toArray());
    }

    protected function broadcast($name, $data)
    {
        //name `office_events` - connection from config/broadcasting
        $this->manager->driver('office_events')->broadcast([''], $name, $data);
    }
}
