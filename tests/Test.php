<?php


namespace Nicodinus\PhpAsync\StdinWrapper\Tests;


use Amp\ByteStream\InputStream;
use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Nicodinus\PhpAsync\StdinWrapper\AsyncStdinWrapper;
use Nicodinus\PhpAsync\StdinWrapper\Utils;
use function Amp\asyncCall;
use function Amp\delay;
use function Amp\Promise\first;

class Test extends AsyncTestCase
{
    public function testNonBlockedStdin()
    {
        if (!Utils::isWindowsOS()) {
            $this->markTestSkipped("This test useful only at Windows!");
        }

        /** @var InputStream $stdin */
        $stdin = yield AsyncStdinWrapper::create();

        $defer = new Deferred();
        asyncCall(function () use (&$defer) {

            yield delay(100);

            $defer->resolve();

        });

        $result = yield first([
            $defer->promise(),
            $stdin->read(),
        ]);
        $this->assertNull($result);

    }
}