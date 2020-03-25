<?php

namespace NSQClient\Connection;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\PoolMissingSocketException;
use NSQClient\Logger\Logger;
use NSQClient\Utils\GracefulShutdown;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

/**
 * Class Pool
 * @package NSQClient\Connection
 */
class Pool
{
    /**
     * @var Nsqd[]
     */
    private static array $instances = [];

    /**
     * @var Stream[]
     */
    private static array $sockMaps = [];

    /**
     * @var LoopInterface
     */
    private static ?LoopInterface $evLoops = null;

    /**
     * @var int
     */
    private static int $evAttached = 0;

    /**
     * @return Nsqd[]
     */
    public static function instances(): array
    {
        return self::$instances;
    }

    /**
     * @param string[] $factors
     * @param callable $creator
     * @return Nsqd
     */
    public static function register(array $factors, callable $creator): Nsqd
    {
        $insKey = self::getInsKey($factors);

        if (isset(self::$instances[$insKey])) {
            return self::$instances[$insKey];
        }

        return self::$instances[$insKey] = call_user_func($creator);
    }

    /**
     * @param resource $socket
     * @return Stream
     * @throws PoolMissingSocketException
     */
    public static function search($socket): Stream
    {
        $expectSockID = (int) $socket;

        if (isset(self::$sockMaps[$expectSockID])) {
            return self::$sockMaps[$expectSockID];
        }

        foreach (self::$instances as $nsqd) {
            if ($nsqd->getSockID() === $expectSockID) {
                self::$sockMaps[$nsqd->getSockID()] = $nsqd->getSockIns();
                return $nsqd->getSockIns();
            }
        }

        throw new PoolMissingSocketException();
    }

    /**
     * @return LoopInterface
     */
    public static function getEvLoop(): LoopInterface
    {
        if (is_null(self::$evLoops)) {
            self::$evLoops = Factory::create();
            GracefulShutdown::init(self::$evLoops);
        }
        return self::$evLoops;
    }

    /**
     * New attach by consumer connects
     */
    public static function setEvAttached(): void
    {
        self::$evAttached++;
    }

    /**
     * New detach by consumer closing
     */
    public static function setEvDetached(): void
    {
        self::$evAttached--;
        if (self::$evAttached <= 0) {
            Logger::getInstance()->info('ALL event detached ... perform shutdown');
            if (self::$evLoops instanceof LoopInterface) {
                self::$evLoops->stop();
            }
        }
    }

    /**
     * @param string[] $factors
     * @return string
     */
    private static function getInsKey(array $factors): string
    {
        return implode('$', $factors);
    }
}
