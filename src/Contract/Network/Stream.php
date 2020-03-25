<?php

namespace NSQClient\Contract\Network;

/**
 * Interface Stream
 * @package NSQClient\Contract\Network
 */
interface Stream
{
    /**
     * @param string $buf
     */
    public function write(string $buf): void;

    /**
     * @param int $len
     * @return string
     */
    public function read(int $len): string;
}
