<?php

namespace NSQClient\Message;

use NSQClient\Connection\Nsqd;
use NSQClient\Contract\Message as MessageInterface;

/**
 * Class Message
 * @package NSQClient\Message
 */
class Message implements MessageInterface
{
    /**
     * @var string
     */
    private ?string $id = null;

    /**
     * @var string
     */
    private ?string $payload = null;

    /**
     * @var mixed
     */
    private $data = null;

    /**
     * @var int
     */
    private ?int $attempts = null;

    /**
     * @var int
     */
    private ?int $timestamp = null;

    /**
     * @var int
     */
    private ?int $deferred = null;

    /**
     * @var Nsqd
     */
    private ?Nsqd $nsqd = null;

    /**
     * Message constructor.
     * @param string        $payload
     * @param string|null   $id
     * @param int|null      $attempts
     * @param int|null      $timestamp
     * @param Nsqd|null     $nsqd
     */
    public function __construct(
        string $payload,
        ?string $id = null,
        ?int $attempts = null,
        ?int $timestamp = null,
        ?Nsqd $nsqd = null
    ) {
        $this->id = $id;
        $this->payload = $payload;
        $this->attempts = $attempts;
        $this->timestamp = $timestamp;
        $this->data = !is_null($id) ? json_decode($payload, true) : json_encode($payload);
        $this->nsqd = $nsqd;
    }

    /**
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function payload(): string
    {
        return $this->payload;
    }

    /**
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * @return int
     */
    public function timestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * just done
     */
    public function done(): void
    {
        $this->nsqd->finish($this->id);
    }

    /**
     * just retry
     */
    public function retry(): void
    {
        $this->delay(0);
    }

    /**
     * just delay
     * @param int $seconds
     */
    public function delay(int $seconds): void
    {
        $this->nsqd->requeue($this->id, $seconds * 1000);
    }

    /**
     * @param int|null $seconds
     * @return int|null
     */
    public function deferred(?int $seconds = null): ?int
    {
        if (is_null($seconds)) {
            return $this->deferred;
        } else {
            return $this->deferred = $seconds * 1000;
        }
    }
}
