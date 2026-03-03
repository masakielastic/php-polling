# masakielastic/polling

`masakielastic/polling` is a Composer-installable reference implementation for the PHP RFC Polling API.

## Install

```bash
composer require masakielastic/polling
```

## Status

This package currently provides a `stream_select()`-based implementation.

- `Io\Poll\Backend::Poll` is available.
- `Io\Poll\Backend::Auto` resolves to `Poll`.
- `StreamPollHandle` is the only supported handle type.
- `Event::EdgeTriggered` is not supported.

## Usage

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\StreamPollHandle;

$context = new Context();
$stream = fopen('php://temp', 'r+');
fwrite($stream, "hello\n");
rewind($stream);

$watcher = $context->add(new StreamPollHandle($stream), [Event::Read], 'demo');
$ready = $context->wait(0, 1000);

foreach ($ready as $triggered) {
    var_dump($triggered->getData(), $triggered->getTriggeredEvents());
}
```

## Examples

- [TCP server example](examples/basic_tcp_server.php)
- [TCP client example](examples/basic_tcp_client.php)

Run them from the repository root after installing dependencies:

```bash
php examples/basic_tcp_server.php
php examples/basic_tcp_client.php
```

## Development

```bash
composer validate
composer test
```
