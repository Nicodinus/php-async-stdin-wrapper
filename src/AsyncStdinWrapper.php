<?php


namespace Nicodinus\PhpAsync\StdinWrapper;


use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\Failure;
use Amp\Promise;
use Nicodinus\PhpAsync\StdinWrapper\Internal\Windows\WindowsWrapper;
use RuntimeException;
use function Amp\ByteStream\getStdin;
use function Amp\call;
use function is_resource;
use const STDERR;
use const STDIN;
use const STDOUT;

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
     * @param resource|null $stdin
     * @param resource|false $stdout
     * @param resource|false $stderr
     *
     * @return Promise<static>|Failure<RuntimeException>
     */
    public static function create($stdin = null, $stdout = STDOUT, $stderr = STDERR): Promise
    {
        return call(function () use (&$stdin, &$stdout, &$stderr) {

            if (!$stdin || !is_resource($stdin)) {
                $stdin = STDIN;
            }

            if (!stream_set_blocking($stdin, false)) {
                $stdinStream = yield WindowsWrapper::create($stdin, $stdout, $stderr);
            } else {
                if ($stdin === STDIN) {
                    $stdinStream = getStdin();
                } else {
                    $stdinStream = new ResourceInputStream($stdin);
                }
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
        } else if ($this->stdinStream instanceof ResourceInputStream) {
            $this->stdinStream->close();
        }
    }
}