<?php

if (!isset($argv[1])) {
    exit(-1);
}

require_once __DIR__ . "/../../../vendor/autoload.php";

use Amp\ByteStream;
use Amp\Loop;
use Amp\Socket;

//

Loop::run(static function () use (&$argv) {

    /** @var Socket\ResourceSocket $resourceSocket */
    $resourceSocket = null;

    try {

        $resourceSocket = yield Socket\connect($argv[1], (new Socket\ConnectContext())
            ->withTcpNoDelay()
        );

    } catch (Throwable $throwable) {
        exit(-1);
    }

    $stdin = ByteStream\getStdin();

    while (true) {

        $data = yield $stdin->read();
        if (!$data) {
            break;
        }

        if ($resourceSocket->isClosed()) {
            break;
        }

        try {
            yield $resourceSocket->write($data);
        } catch (Throwable $throwable) {
            break;
        }

    }

    if (!$resourceSocket->isClosed()) {
        $resourceSocket->close();
    }

});

exit(0);