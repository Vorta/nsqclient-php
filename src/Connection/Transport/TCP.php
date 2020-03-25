<?php

namespace NSQClient\Connection\Transport;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\NetworkSocketException;
use NSQClient\Exception\NetworkTimeoutException;

/**
 * Class TCP
 * @package NSQClient\Connection\Transport
 */
class TCP implements Stream
{
    /**
     * @var string
     */
    private string $host = '127.0.0.1';

    /**
     * @var int
     */
    private int $port = 4150;

    /**
     * @var bool
     */
    private bool $blocking = true;

    /**
     * @var resource|null
     */
    private $socket = null;

    /**
     * @var callable|null
     */
    private $handshake = null;

    /**
     * @var int
     */
    private int $readTimeoutSec = 5;

    /**
     * @var int
     */
    private int $readTimeoutUsec = 0;

    /**
     * @var int
     */
    private int $writeTimeoutSec = 5;

    /**
     * @var int
     */
    private int $writeTimeoutUsec = 0;

    /**
     * @var int
     */
    private int $connRecyclingSec = 0;

    /**
     * @var int
     */
    private int $connEstablishedTime = 0;

    /**
     * @param string $host
     * @param int $port
     */
    public function setTarget(string $host, int $port): void
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @param bool $switch
     */
    public function setBlocking(bool $switch): void
    {
        $this->blocking = $switch ? true : false;
    }

    /**
     * @param string $ch
     * @param float $time
     */
    public function setTimeout(string $ch = 'rw', float $time = 5.0): void
    {
        if ($ch === 'r' || $ch === 'rw') {
            $this->readTimeoutSec = (int) floor($time);
            $this->readTimeoutUsec = (int) ($time - $this->readTimeoutSec) * 1000000;
        }

        if ($ch === 'w' || $ch === 'rw') {
            $this->writeTimeoutSec = (int) floor($time);
            $this->writeTimeoutUsec = (int) ($time - $this->writeTimeoutSec) * 1000000;
        }
    }

    /**
     * @param int $seconds
     */
    public function setRecycling(int $seconds): void
    {
        $this->connRecyclingSec = $seconds;
    }

    /**
     * @param callable $processor
     */
    public function setHandshake(callable $processor): void
    {
        $this->handshake = $processor;
    }

    /**
     * @return resource
     */
    public function socket()
    {
        if (!is_null($this->socket)) {
            if (
                $this->connRecyclingSec
                && $this->connEstablishedTime
                && (time() - $this->connEstablishedTime > $this->connRecyclingSec)
            ) {
                $this->close();
            } else {
                return $this->socket;
            }
        }

        $netErrNo = $netErrMsg = null;

        $socket = fsockopen($this->host, $this->port, $netErrNo, $netErrMsg);

        if ($socket === false) {
            throw new NetworkSocketException(
                "Connecting failed [{$this->host}:{$this->port}] - {$netErrMsg}",
                $netErrNo
            );
        } else {
            $this->socket = $socket;
            $this->connEstablishedTime = time();
        }

        stream_set_blocking($this->socket, $this->blocking);

        if (is_callable($this->handshake)) {
            call_user_func($this->handshake, $this);
        }

        return $this->socket;
    }

    /**
     * @param string $buf
     */
    public function write(string $buf): void
    {
        $null = [];
        $socket = $this->socket();
        $writeCh = [$socket];

        while (strlen($buf) > 0) {
            $writable = stream_select($null, $writeCh, $null, $this->writeTimeoutSec, $this->writeTimeoutUsec);
            if ($writable > 0) {
                $wroteLen = stream_socket_sendto($socket, $buf);
                if ($wroteLen === -1) {
                    throw new NetworkSocketException("Writing failed [{$this->host}:{$this->port}](1)");
                }
                $buf = substr($buf, $wroteLen);
            } elseif ($writable === 0) {
                throw new NetworkTimeoutException("Writing timeout [{$this->host}:{$this->port}]");
            } else {
                throw new NetworkSocketException("Writing failed [{$this->host}:{$this->port}](2)");
            }
        }
    }

    /**
     * @param int $len
     * @return string
     */
    public function read(int $len): string
    {
        $null = null;
        $socket = $this->socket();
        $readCh = [$socket];

        $remainingLen = $len;
        $buffer = '';

        while (strlen($buffer) < $len) {
            $readable = stream_select($readCh, $null, $null, $this->readTimeoutSec, $this->readTimeoutUsec);
            if ($readable > 0) {
                $recv = stream_socket_recvfrom($socket, $remainingLen);
                if ($recv === '') {
                    throw new NetworkSocketException("Reading failed [{$this->host}:{$this->port}](2)");
                } else {
                    $buffer .= $recv;
                    $remainingLen -= strlen($recv);
                }
            } elseif ($readable === 0) {
                throw new NetworkTimeoutException("Reading timeout [{$this->host}:{$this->port}]");
            } else {
                throw new NetworkSocketException("Reading failed [{$this->host}:{$this->port}](3)");
            }
        }

        return $buffer;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        $closed = fclose($this->socket);
        $this->socket = null;
        return $closed;
    }
}
