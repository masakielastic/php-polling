<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\StreamPollHandle;

$client = @stream_socket_client('tcp://127.0.0.1:9000', $errorCode, $errorMessage, 3);
if ($client === false) {
    fwrite(STDERR, "Failed to connect: {$errorMessage} ({$errorCode})\n");
    exit(1);
}

stream_set_blocking($client, false);

$context = new Context();
$watcher = $context->add(new StreamPollHandle($client), [Event::Write, Event::Read, Event::ReadHangUp]);

$sent = false;

while (true) {
    foreach ($context->wait(1) as $ready) {
        if (!$sent && $ready->hasTriggered(Event::Write)) {
            fwrite($client, "hello from client\n");
            $sent = true;
        }

        if ($ready->hasTriggered(Event::Read)) {
            $line = fgets($client);
            if ($line !== false) {
                fwrite(STDOUT, "Received: {$line}");
                fclose($client);
                exit(0);
            }
        }

        if ($ready->hasTriggered(Event::HangUp) || $ready->hasTriggered(Event::ReadHangUp)) {
            fwrite(STDERR, "Server closed the connection\n");
            fclose($client);
            exit(1);
        }
    }
}
