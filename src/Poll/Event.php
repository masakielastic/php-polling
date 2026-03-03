<?php

declare(strict_types=1);

namespace Io\Poll;

enum Event
{
    case Read;
    case Write;
    case Error;
    case HangUp;
    case ReadHangUp;
    case OneShot;
    case EdgeTriggered;
}
