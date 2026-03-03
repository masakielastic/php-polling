<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\StreamPollHandle;

$server = @stream_socket_server('tcp://127.0.0.1:8000', $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "Failed to start server: {$errorMessage} ({$errorCode})\n");
    exit(1);
}

stream_set_blocking($server, false);

$context = new Context();
$serverWatcher = $context->add(new StreamPollHandle($server), [Event::Read], 'server');
$clients = [];

fwrite(STDOUT, "Listening on 127.0.0.1:8000\n");

while (true) {
    foreach ($context->wait(1) as $watcher) {
        if ($watcher === $serverWatcher) {
            $client = @stream_socket_accept($server, 0);
            if ($client === false) {
                continue;
            }

            stream_set_blocking($client, false);
            $id = (int) $client;
            $clients[$id] = $context->add(new StreamPollHandle($client), [Event::Read, Event::ReadHangUp], $id);
            fwrite(STDOUT, "Accepted client {$id}\n");
            continue;
        }

        $stream = $watcher->getHandle()->getStream();
        $id = $watcher->getData();

        if ($watcher->hasTriggered(Event::HangUp) || $watcher->hasTriggered(Event::ReadHangUp)) {
            fclose($stream);
            unset($clients[$id]);
            fwrite(STDOUT, "Client {$id} disconnected\n");
            continue;
        }

        $line = fgets($stream);
        if ($line === false) {
            continue;
        }

        fwrite(STDOUT, "Received from {$id}: {$line}");
        fwrite($stream, "echo: {$line}");
    }
}
