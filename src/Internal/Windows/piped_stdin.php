<?php

if (!isset($argv[1]) || !is_dir($argv[1]) || !is_file($argv[1] . DIRECTORY_SEPARATOR . "autoload.php")) {
    exit(-1);
}

if (!isset($argv[2])) {
    exit(-2);
}

require_once $argv[1] . DIRECTORY_SEPARATOR . "autoload.php";

use Amp\ByteStream;
use Amp\Loop;
use Amp\Socket;

//

Loop::run(static function () use (&$argv) {

    /** @var Socket\ResourceSocket $resourceSocket */
    $resourceSocket = null;

    try {

        $resourceSocket = yield Socket\connect($argv[2], (new Socket\ConnectContext())
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