<?php

namespace NSQClient;

use Psr\Log\LoggerInterface;

/**
 * Class SDK
 * @package NSQClient
 */
class SDK
{
    /**
     * sdk version
     */
    public const VERSION = '2.0';

    /**
     * amazing name
     */
    public const NAME = 'nsqclient';

    /**
     * @var int
     */
    public static int $pubRecyclingSec = 45;

    /**
     * @var LoggerInterface|null
     */
    public static ?LoggerInterface $presentLogger = null;

    /**
     * @var bool
     */
    public static $enabledStringPack = true;

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$presentLogger = $logger;
    }

    /**
     * @param bool $enable
     */
    public static function setStringPack(bool $enable): void
    {
        self::$enabledStringPack = $enable;
    }
}
