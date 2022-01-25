<?php

namespace October\Rain\Events;

use Str;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

/**
 * GlobalDispatcher is a custom event dispatcher for October CMS
 *
 * @package october\events
 * @author Alexey Bobkov, Samuel Georges
 */
class GlobalDispatcher
{
    use \Illuminate\Support\Traits\ForwardsCalls;
    use Concerns\HasListener;
    use Concerns\HasTrigger;

    /**
     * @var array listeners that are registered.
     */
    protected $listeners = [];

    /**
     * @var array wildcards are catch-all listeners.
     */
    protected $wildcards = [];

    /**
     * @var array wildcardsCache
     */
    protected $wildcardsCache = [];

    /**
     * @var array sorted event listeners.
     */
    protected $sorted = [];

    /**
     * @var array firing stack for events.
     */
    protected $firing = [];

    /**
     * @var DispatcherContract laravelEvents instance.
     */
    protected $laravelEvents;

    /**
     * listen registers an event listener with the dispatcher.
     * @param string|array $events
     * @param mixed $listener
     * @param int $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0)
    {
        if ($priority === 0) {
            $this->laravelEvents->listen($events, $listener);
        }
        else {
            $this->listenGlobally($events, $listener, $priority);
        }
    }

    /**
     * fire an event and call the listeners.
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    public function fire($event, $payload = [], $halt = false)
    {
        return $this->dispatchGlobally($event, $payload, $halt);
    }

    /**
     * firing gets the event that is currently firing.
     * @return string
     */
    public function firing()
    {
        return last($this->firing);
    }

    /**
     * forget removes a set of listeners from the dispatcher.
     * @param  string  $event
     * @return void
     */
    public function forget($event)
    {
        if (Str::contains($event, '*')) {
            unset($this->wildcards[$event]);
        }
        else {
            unset($this->listeners[$event], $this->sorted[$event]);
        }

        $this->laravelEvents->forget($event);
    }

    /**
     * setLaravelDispatcher sets the queue resolver implementation.
     */
    public function setLaravelDispatcher(DispatcherContract $dispatcher): GlobalDispatcher
    {
        $this->laravelEvents = $dispatcher;

        return $this;
    }

    /**
     * __call magic
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(
            $this->laravelEvents,
            $method,
            $parameters
        );
    }
}