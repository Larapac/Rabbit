<?php

namespace App\Listeners;

use App\Contracts\Foundation\EventSubscriber;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

abstract class BaseEventSubscriber implements EventSubscriber
{
    /**
     * Выполнена ли загрузка.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Внедрение контейнера
     *
     * @var Container
     */
    protected static $container;

    /**
     * @param Container $container
     */
    public static function setContainer(Container $container)
    {
        self::$container = $container;
    }

    /**
     * Хелпер для добавления подписки на события Eloquent ORM.
     *
     * @param Dispatcher $dispatcher
     * @param string|string[] $class
     * @param string $event
     * @param mixed $handler
     */
    public function subscribeEloquentEvent(Dispatcher $dispatcher, $class, $event = 'updated', $handler = null)
    {
        foreach ((array) $class as $class_name) {
            $resolved_handler = $handler ?: 'on' . class_basename($class_name) . title_case($event);
            $resolved_handler = is_string($resolved_handler) ? $this->handler($resolved_handler) : $resolved_handler;
            $dispatcher->listen("eloquent.{$event}: {$class_name}", $resolved_handler);
        }
    }

    /**
     * Метод для создания обработчика события.
     *
     * @param string $handler имя метода
     *
     * @return callable
     */
    public function handler($handler)
    {
        return function () use ($handler) {
            $this->booting();
            call_user_func_array([$this, $handler], func_get_args());
        };
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return self::$container;
    }

    /**
     * Выполняет действия перед первой обработкой события.
     */
    protected function booting()
    {
        if ($this->booted || null === ($container = $this->getContainer())) {
            return;
        }
        if (method_exists($this, 'boot')) {
            $container->call([$this, 'boot']);
        }
        $this->booted = true;
    }
}
