<?php

declare(strict_types=1);

namespace Io\Poll;

abstract class FailedPollOperationException extends PollException
{
    public const int ERROR_NONE = 0;
    public const int ERROR_SYSTEM = 1;
    public const int ERROR_NOMEM = 2;
    public const int ERROR_INVALID = 3;
    public const int ERROR_EXISTS = 4;
    public const int ERROR_NOTFOUND = 5;
    public const int ERROR_TIMEOUT = 6;
    public const int ERROR_INTERRUPTED = 7;
    public const int ERROR_PERMISSION = 8;
    public const int ERROR_TOOBIG = 9;
    public const int ERROR_AGAIN = 10;
    public const int ERROR_NOSUPPORT = 11;
}
