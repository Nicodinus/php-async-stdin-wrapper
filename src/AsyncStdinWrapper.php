<?php


namespace Nicodinus\PhpAsync\StdinWrapper;


use Amp\ByteStream\InputStream;
use Amp\Failure;
use Amp\Promise;
use Nicodinus\PhpAsync\StdinWrapper\Internal\Windows\WindowsWrapper;
use RuntimeException;
use function Amp\ByteStream\getStdin;
use function Amp\call;

/**
 * Class AsyncStdinWrapper
 *
 * @package Nicodinus\PhpAsync\StdinWrapper
 */
final class AsyncStdinWrapper implements InputStream
{
    /** @var InputStream */
    private InputStream $stdinStream;

    //

    /**
     * AsyncStdinWrapper constructor.
     *
     * @param InputStream $stdinStream
     */
    protected function __construct(InputStream $stdinStream)
    {
        $this->stdinStream = $stdinStream;
    }

    /**
     * @return Promise<static>|Failure<RuntimeException>
     */
    public static function create(): Promise
    {
        return call(function () {

            if (Utils::isWindowsOS()) {
                $stdinStream = yield WindowsWrapper::create();
            } else {
                $stdinStream = getStdin();
            }

            return new static($stdinStream);

        });
    }

    /**
     * @inheritDoc
     */
    public function read(): Promise
    {
        return $this->stdinStream->read();
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if ($this->stdinStream instanceof WindowsWrapper) {
            $this->stdinStream->close();
        }
    }
}