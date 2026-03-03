<?php

declare(strict_types=1);

namespace Io\Poll;

final class Context
{
    private Backend $backend;

    /** @var array<int, Watcher> */
    private array $watchers = [];

    public function __construct(Backend $backend = Backend::Auto)
    {
        if (!$backend->isAvailable()) {
            throw new BackendUnavailableException(
                "Backend {$backend->name} is not available",
            );
        }

        $this->backend = $backend === Backend::Auto ? Backend::Poll : $backend;
    }

    public function add(Handle $handle, array $events, mixed $data = null): Watcher
    {
        $this->assertHandleSupported($handle);
        $this->assertHandleValid($handle);
        $normalizedEvents = $this->normalizeInputEvents($events, FailedHandleAddException::class);

        $streamId = $this->streamId($handle);
        if (isset($this->watchers[$streamId])) {
            throw new HandleAlreadyWatchedException('Handle is already being watched');
        }

        $watcher = new Watcher($this, $handle, $normalizedEvents, $data);
        $this->watchers[$streamId] = $watcher;

        return $watcher;
    }

    public function wait(
        int $timeoutSeconds = -1,
        int $timeoutMicroseconds = 0,
        int $maxEvents = -1,
    ): array {
        foreach ($this->watchers as $watcher) {
            $watcher->replaceTriggeredEvents([]);
        }

        if ($this->watchers === []) {
            if ($timeoutSeconds > 0 || $timeoutMicroseconds > 0) {
                [$seconds, $microseconds] = $this->normalizeTimeout($timeoutSeconds, $timeoutMicroseconds);
                if ($seconds !== null) {
                    \usleep(($seconds * 1000000) + $microseconds);
                }
            }

            return [];
        }

        [$seconds, $microseconds] = $this->normalizeTimeout($timeoutSeconds, $timeoutMicroseconds);

        $read = [];
        $write = [];
        $except = [];
        $watchersByStream = [];

        foreach ($this->watchers as $streamId => $watcher) {
            $handle = $watcher->getHandle();
            \assert($handle instanceof StreamPollHandle);

            if (!$handle->isValid()) {
                $watcher->replaceTriggeredEvents([Event::Error, Event::HangUp]);
                $this->removeWatcher($watcher);
                continue;
            }

            $stream = $handle->getStream();
            $watchersByStream[$streamId] = $watcher;

            if ($this->watchesRead($watcher)) {
                $read[] = $stream;
            }

            if ($this->watchesWrite($watcher)) {
                $write[] = $stream;
            }

            $except[] = $stream;
        }

        if ($watchersByStream === []) {
            return [];
        }

        $selected = @\stream_select($read, $write, $except, $seconds, $microseconds);
        if ($selected === false) {
            throw new FailedPollWaitException(
                'stream_select() failed',
                FailedPollOperationException::ERROR_SYSTEM,
            );
        }

        if ($selected === 0) {
            return [];
        }

        $readyRead = $this->indexReadyStreams($read);
        $readyWrite = $this->indexReadyStreams($write);
        $readyExcept = $this->indexReadyStreams($except);

        $triggeredWatchers = [];
        foreach ($watchersByStream as $streamId => $watcher) {
            $triggeredEvents = [];

            if (isset($readyRead[$streamId])) {
                $triggeredEvents[] = Event::Read;

                if ($this->isReadHangUp($watcher)) {
                    $triggeredEvents[] = Event::HangUp;
                    if ($this->watchesReadHangUp($watcher)) {
                        $triggeredEvents[] = Event::ReadHangUp;
                    }
                }
            }

            if (isset($readyWrite[$streamId])) {
                $triggeredEvents[] = Event::Write;
            }

            if (isset($readyExcept[$streamId])) {
                $triggeredEvents[] = Event::Error;
            }

            if ($triggeredEvents === []) {
                continue;
            }

            $watcher->replaceTriggeredEvents($triggeredEvents);
            $triggeredWatchers[] = $watcher;

            if ($this->watchesOneShot($watcher)) {
                $this->removeWatcher($watcher);
            }

            if ($maxEvents > 0 && \count($triggeredWatchers) >= $maxEvents) {
                break;
            }
        }

        return $triggeredWatchers;
    }

    public function getBackend(): Backend
    {
        return $this->backend;
    }

    public function modifyWatcher(Watcher $watcher, array $events, mixed $data): void
    {
        $handle = $watcher->getHandle();
        $this->assertHandleSupported($handle);
        $this->assertHandleValid($handle);

        $normalizedEvents = $this->normalizeInputEvents($events, FailedWatcherModificationException::class);
        $watcher->replaceConfiguration($normalizedEvents, $data);
    }

    public function removeWatcher(Watcher $watcher): void
    {
        $streamId = $this->streamId($watcher->getHandle());
        unset($this->watchers[$streamId]);
        $watcher->deactivate();
    }

    private function normalizeInputEvents(array $events, string $exceptionClass): array
    {
        if ($events === []) {
            throw new $exceptionClass(
                'At least one event must be specified',
                FailedPollOperationException::ERROR_INVALID,
            );
        }

        $normalized = [];
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                throw new \TypeError('Events must be instances of ' . Event::class);
            }

            if ($event === Event::Error || $event === Event::HangUp) {
                throw new $exceptionClass(
                    "{$event->name} is an output-only event",
                    FailedPollOperationException::ERROR_INVALID,
                );
            }

            if ($event === Event::EdgeTriggered) {
                throw new $exceptionClass(
                    'Edge-triggered polling is not supported by the stream_select() backend',
                    FailedPollOperationException::ERROR_NOSUPPORT,
                );
            }

            $normalized[$event->name] = $event;
        }

        return \array_values($normalized);
    }

    private function assertHandleSupported(Handle $handle): void
    {
        if (!$handle instanceof StreamPollHandle) {
            throw new FailedHandleAddException(
                'Only StreamPollHandle is supported by this implementation',
                FailedPollOperationException::ERROR_NOSUPPORT,
            );
        }
    }

    private function assertHandleValid(Handle $handle): void
    {
        \assert($handle instanceof StreamPollHandle);

        if (!$handle->isValid()) {
            throw new InvalidHandleException('Handle is no longer valid for polling');
        }
    }

    private function streamId(Handle $handle): int
    {
        \assert($handle instanceof StreamPollHandle);

        return (int) $handle->getStream();
    }

    private function normalizeTimeout(int $timeoutSeconds, int $timeoutMicroseconds): array
    {
        if ($timeoutSeconds < -1) {
            throw new FailedPollWaitException(
                'Timeout seconds must be -1 or greater',
                FailedPollOperationException::ERROR_INVALID,
            );
        }

        if ($timeoutSeconds === -1) {
            return [null, null];
        }

        if ($timeoutMicroseconds < 0) {
            throw new FailedPollWaitException(
                'Timeout microseconds must be 0 or greater',
                FailedPollOperationException::ERROR_INVALID,
            );
        }

        $timeoutSeconds += intdiv($timeoutMicroseconds, 1000000);
        $timeoutMicroseconds %= 1000000;

        return [$timeoutSeconds, $timeoutMicroseconds];
    }

    private function indexReadyStreams(array $streams): array
    {
        $indexed = [];
        foreach ($streams as $stream) {
            $indexed[(int) $stream] = true;
        }

        return $indexed;
    }

    private function watchesRead(Watcher $watcher): bool
    {
        $events = $watcher->getWatchedEvents();

        return \in_array(Event::Read, $events, true) || \in_array(Event::ReadHangUp, $events, true);
    }

    private function watchesWrite(Watcher $watcher): bool
    {
        return \in_array(Event::Write, $watcher->getWatchedEvents(), true);
    }

    private function watchesReadHangUp(Watcher $watcher): bool
    {
        return \in_array(Event::ReadHangUp, $watcher->getWatchedEvents(), true);
    }

    private function watchesOneShot(Watcher $watcher): bool
    {
        return \in_array(Event::OneShot, $watcher->getWatchedEvents(), true);
    }

    private function isReadHangUp(Watcher $watcher): bool
    {
        $handle = $watcher->getHandle();
        \assert($handle instanceof StreamPollHandle);

        $stream = $handle->getStream();

        return \is_resource($stream) && \feof($stream);
    }
}
