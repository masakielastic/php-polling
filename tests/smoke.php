<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\FailedHandleAddException;
use Io\Poll\HandleAlreadyWatchedException;
use Io\Poll\StreamPollHandle;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

$context = new Context();

$stream = fopen('php://temp', 'r+');
fwrite($stream, "payload\n");
rewind($stream);

$watcher = $context->add(new StreamPollHandle($stream), [Event::Read], 'read-test');
$ready = $context->wait(0, 1000);

assertSame(1, count($ready), 'Expected one ready watcher for readable stream');
assertTrue($ready[0] === $watcher, 'Expected the original watcher instance');
assertTrue($watcher->hasTriggered(Event::Read), 'Expected Read to be triggered');
assertSame('read-test', $watcher->getData(), 'Expected watcher data to be preserved');

$duplicateCaught = false;
try {
    $context->add(new StreamPollHandle($stream), [Event::Read]);
} catch (HandleAlreadyWatchedException) {
    $duplicateCaught = true;
}
assertTrue($duplicateCaught, 'Expected duplicate handle registration to fail');

$invalidEventCaught = false;
try {
    $context->add(new StreamPollHandle(fopen('php://temp', 'r+')), [Event::HangUp]);
} catch (FailedHandleAddException $exception) {
    $invalidEventCaught = $exception->getCode() === \Io\Poll\FailedPollOperationException::ERROR_INVALID;
}
assertTrue($invalidEventCaught, 'Expected HangUp registration to fail with ERROR_INVALID');

$oneShotStream = fopen('php://temp', 'r+');
fwrite($oneShotStream, "x");
rewind($oneShotStream);

$oneShotContext = new Context();
$oneShotWatcher = $oneShotContext->add(new StreamPollHandle($oneShotStream), [Event::Read, Event::OneShot]);
$oneShotReady = $oneShotContext->wait(0, 1000);

assertSame(1, count($oneShotReady), 'Expected one-shot watcher to trigger once');
assertTrue(!$oneShotWatcher->isActive(), 'Expected one-shot watcher to be removed after trigger');
fclose($stream);
fclose($oneShotStream);

fwrite(STDOUT, "Smoke tests passed\n");
