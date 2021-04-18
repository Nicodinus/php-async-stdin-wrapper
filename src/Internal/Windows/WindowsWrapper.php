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
use function Amp\delay;
use function escapeshellarg;
use function is_resource;
use function proc_get_status;
use function proc_open;
use const STDERR;
use const STDIN;
use const STDOUT;

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
     * @param resource|null $stdin
     * @param resource|false $stdout
     * @param resource|false $stderr
     *
     * @return Promise<static>|Failure<RuntimeException>
     */
    public static function create($stdin = null, $stdout = STDOUT, $stderr = STDERR): Promise
    {
        return call(function () use (&$stdin, &$stdout, &$stderr) {

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
                0 => $stdin && is_resource($stdin) ? $stdin : STDIN,
            ];

            if ($stdout && is_resource($stdout)) {
                $descriptors[1] = $stdout;
            }

            if ($stderr && is_resource($stderr)) {
                $descriptors[2] = $stderr;
            }

            $processHandle = @proc_open(
                'php ' . escapeshellarg(__DIR__ . '/piped_stdin.php')
                . ' ' . escapeshellarg(Utils::getComposerVendorPath())
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

            $initialized = false;

            asyncCall(function () use (&$processHandle, &$timeoutWatcher, &$serverHandle, &$initialized) {

                while (!$initialized && @proc_get_status($processHandle)['running']) {
                    yield delay(10);
                }

                if (@proc_get_status($processHandle)['running']) {
                    return;
                }

                if ($timeoutWatcher) {
                    Loop::cancel($timeoutWatcher);
                    $timeoutWatcher = null;
                }

                if (!$serverHandle->isClosed()) {
                    $serverHandle->close();
                }

            });

            /** @var ResourceSocket|null $resourceSocket */
            $resourceSocket = yield $resourceSocketDefer->promise();

            $initialized = true;

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