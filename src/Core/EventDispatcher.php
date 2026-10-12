<?php

namespace App\Core;

class EventDispatcher
{
    private static array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string $eventClass
     * @param string|callable $listener
     */
    public static function listen(string $eventClass, $listener): void
    {
        if (!isset(self::$listeners[$eventClass])) {
            self::$listeners[$eventClass] = [];
        }
        self::$listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event
     */
    public static function dispatch(object $event): void
    {
        $eventClass = get_class($event);
        
        if (isset(self::$listeners[$eventClass])) {
            foreach (self::$listeners[$eventClass] as $listener) {
                if (is_callable($listener)) {
                    call_user_func($listener, $event);
                } elseif (is_string($listener) && class_exists($listener)) {
                    $instance = new $listener();
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($event);
                    }
                }
            }
        }
    }
}
