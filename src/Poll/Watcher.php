<?php

declare(strict_types=1);

namespace Io\Poll;

final class Watcher
{
    private array $watchedEvents;
    private array $triggeredEvents = [];
    private bool $active = true;

    public function __construct(
        private readonly Context $context,
        private readonly Handle $handle,
        array $events,
        private mixed $data = null,
    ) {
        $this->watchedEvents = self::normalizeEvents($events);
    }

    public function getHandle(): Handle
    {
        return $this->handle;
    }

    public function getWatchedEvents(): array
    {
        return $this->watchedEvents;
    }

    public function getTriggeredEvents(): array
    {
        return $this->triggeredEvents;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function hasTriggered(Event $event): bool
    {
        return \in_array($event, $this->triggeredEvents, true);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function modify(array $events, mixed $data = null): void
    {
        $this->assertActive();
        $this->context->modifyWatcher($this, $events, $data);
    }

    public function modifyEvents(array $events): void
    {
        $this->assertActive();
        $this->context->modifyWatcher($this, $events, $this->data);
    }

    public function modifyData(mixed $data): void
    {
        $this->assertActive();
        $this->data = $data;
    }

    public function remove(): void
    {
        $this->assertActive();
        $this->context->removeWatcher($this);
    }

    public function replaceConfiguration(array $events, mixed $data): void
    {
        $this->watchedEvents = self::normalizeEvents($events);
        $this->data = $data;
    }

    public function replaceTriggeredEvents(array $events): void
    {
        $this->triggeredEvents = self::normalizeEvents($events);
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->triggeredEvents = [];
    }

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new InactiveWatcherException('Watcher is no longer active');
        }
    }

    private static function normalizeEvents(array $events): array
    {
        $unique = [];
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                throw new \TypeError('Events must be instances of ' . Event::class);
            }

            $unique[$event->name] = $event;
        }

        return \array_values($unique);
    }
}
