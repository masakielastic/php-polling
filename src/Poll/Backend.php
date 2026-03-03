<?php

declare(strict_types=1);

namespace Io\Poll;

enum Backend
{
    case Auto;
    case Poll;
    case Epoll;
    case Kqueue;
    case EventPorts;
    case WSAPoll;

    public static function getAvailableBackends(): array
    {
        return [self::Poll];
    }

    public function isAvailable(): bool
    {
        return match ($this) {
            self::Auto, self::Poll => true,
            default => false,
        };
    }

    public function supportsEdgeTriggering(): bool
    {
        return false;
    }
}
