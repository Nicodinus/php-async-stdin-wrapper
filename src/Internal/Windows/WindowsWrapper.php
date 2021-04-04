<?php


namespace Nicodinus\PhpAsync\StdinWrapper\Internal\Windows;


use Amp\ByteStream\InputStream;
use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Server;
use Nicodinus\PhpAsync\StdinWrapper\Utils;
use RuntimeException;
use function Amp\asyncCall;
use function Amp\call;
use function escapeshellarg;
use function is_resource;
use function proc_get_status;
use function proc_open;

/**
 * Class WindowsWrapper
 *
 * @package Nicodinus\PhpAsync\StdinWrapper\Internal\Windows
 *
 * @internal
 */
final class WindowsWrapper implements InputStream
{
    /** @var ResourceSocket */
    private ResourceSocket $resourceSocket;

    /** @var resource|null */
    private $processHandle;

    //

    /**
     * WindowsWrapper constructor.
     *
     * @param ResourceSocket $resourceSocket
     * @param resource $processHandle
     */
    protected function __construct(ResourceSocket $resourceSocket, $processHandle)
    {
        $this->resourceSocket = $resourceSocket;
        $this->processHandle = $processHandle;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return Promise<static>|Failure<RuntimeException>
     */
    public static function create(): Promise
    {
        return call(function () {

            $serverHandle = Server::listen("tcp://127.0.0.1:0", (new BindContext())
                ->withReusePort()
                ->withTcpNoDelay()
            );

            if ($serverHandle->isClosed()) {
                throw new RuntimeException("Failed to initialize socket server!");
            }

            $resourceSocketDefer = new Deferred();

            $timeoutWatcher = Loop::delay(5000, function () use (&$timeoutWatcher, &$serverHandle) {

                $timeoutWatcher = null;

                $serverHandle->close();

            });

            asyncCall(function () use (&$serverHandle, &$resourceSocketDefer, &$timeoutWatcher) {

                $resourceSocket = yield $serverHandle->accept();

                if ($timeoutWatcher) {
                    Loop::cancel($timeoutWatcher);
                    $timeoutWatcher = null;
                }

                if (!$serverHandle->isClosed()) {
                    $serverHandle->close();
                }

                $resourceSocketDefer->resolve($resourceSocket);

            });

            $descriptors = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ];

            $processHandle = @proc_open(
                'php ' . escapeshellarg(__DIR__ . '/piped_stdin.php')
                . ' ' . escapeshellarg($serverHandle->getAddress()->toString())
                ,
                $descriptors,
                $pipes,
                null,
                null,
                [
                    'bypass_shell' => true,
                    'blocking_pipes' => false,
                ],
            );

            if (!$processHandle || !is_resource($processHandle) || !@proc_get_status($processHandle)['running']) {
                throw new RuntimeException("Failed to initialize loopback process!");
            }

            /** @var ResourceSocket|null $resourceSocket */
            $resourceSocket = yield $resourceSocketDefer->promise();
            if (!$resourceSocket) {

                if (@proc_get_status($processHandle)['running']) {
                    Utils::killProcess(proc_get_status($processHandle)['pid']);
                }

                throw new RuntimeException("Failed to initialize loopback socket stream!");

            }

            return new static($resourceSocket, $processHandle);

        });
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if (!$this->resourceSocket->isClosed()) {
            $this->resourceSocket->close();
        }

        if ($this->processHandle && is_resource($this->processHandle)) {

            if (@proc_get_status($this->processHandle)['running']) {
                Utils::killProcess(proc_get_status($this->processHandle)['pid']);
            }

            $this->processHandle = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function read(): Promise
    {
        return $this->resourceSocket->read();
    }
}