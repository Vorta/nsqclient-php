<?php

namespace NSQClient\Contract;

/**
 * Interface Message
 * @package NSQClient\Contract
 */
interface Message
{
    /**
     * Get message ID
     * @return string
     */
    public function id(): string;

    /**
     * Get message payload (raw)
     * @return string
     */
    public function payload(): string;

    /**
     * Get message data (serialized/un-serialized)
     * @return mixed
     */
    public function data();

    /**
     * Get attempts
     * @return int
     */
    public function attempts(): int;

    /**
     * Get timestamp
     * @return int
     */
    public function timestamp(): int;

    /**
     * Make msg is done
     */
    public function done(): void;

    /**
     * Make retry with msg
     */
    public function retry(): void;

    /**
     * Make delay with msg
     * @param int $seconds
     */
    public function delay(int $seconds): void;

    /**
     * Set msg deferred or get msg's deferred milliseconds
     * @param int|null $seconds
     * @return int|self
     */
    public function deferred(?int $seconds = null);
}
