<?php

declare(strict_types=1);

namespace Io\Poll;

abstract class Handle
{
    abstract protected function getFileDescriptor(): int;
}
