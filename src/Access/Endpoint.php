<?php

namespace NSQClient\Access;

use NSQClient\Exception\InvalidLookupdException;

/**
 * Class Endpoint
 * @package NSQClient\Access
 */
class Endpoint
{
    /**
     * @var string
     */
    private string $lookupd = 'http://nsqlookupd.local.moyo.im:4161';

    /**
     * @var string
     */
    private string $uniqueID = 'hash';

    /**
     * Endpoint constructor.
     * @param string $lookupd
     * @throws InvalidLookupdException
     */
    public function __construct(string $lookupd)
    {
        $this->lookupd = $lookupd;
        $this->uniqueID = spl_object_hash($this);

        // checks
        $parsed = parse_url($this->lookupd);
        if (!is_array($parsed) || !array_key_exists('host', $parsed)) {
            throw new InvalidLookupdException();
        }
    }

    /**
     * @return string
     */
    public function getUniqueID(): string
    {
        return $this->uniqueID;
    }

    /**
     * @return string
     */
    public function getLookupd(): string
    {
        return $this->lookupd;
    }

    /**
     * @return string
     */
    public function getConnType(): string
    {
        return PHP_SAPI === 'cli' ? 'tcp' : 'http';
    }
}
