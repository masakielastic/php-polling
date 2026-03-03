<?php

declare(strict_types=1);

namespace Io\Poll;

final class StreamPollHandle extends Handle
{
    private mixed $stream;

    public function __construct($stream)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new InvalidHandleException('StreamPollHandle expects a valid stream resource');
        }

        $this->stream = $stream;
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function isValid(): bool
    {
        // Keep EOF streams valid so wait() can surface remote close as HangUp/Read.
        return \is_resource($this->stream) && \get_resource_type($this->stream) === 'stream';
    }

    protected function getFileDescriptor(): int
    {
        return (int) $this->stream;
    }
}
