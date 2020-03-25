<?php

namespace NSQClient\Utils;

use NSQClient\Connection\Pool;
use NSQClient\Logger\Logger;
use React\EventLoop\LoopInterface;

/**
 * Class GracefulShutdown
 * @package NSQClient\Utils
 */
class GracefulShutdown
{
    /**
     * 500 ms
     * @var float
     */
    private static float $signalDispatchInv = 0.5;

    /**
     * @var array<int, string>
     */
    private static array $acceptSignals = [
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGTERM => 'SIGTERM'
    ];

    /**
     * @param LoopInterface $evLoop
     */
    public static function init(LoopInterface $evLoop): void
    {
        if (extension_loaded('pcntl')) {
            foreach (self::$acceptSignals as $signal => $name) {
                pcntl_signal($signal, [__CLASS__, 'signalHandler']);
            }

            $evLoop->addPeriodicTimer(self::$signalDispatchInv, function () {
                pcntl_signal_dispatch();
            });
        }
    }

    /**
     * @param int $signal
     */
    public static function signalHandler(int $signal): void
    {
        Logger::getInstance()->info('Signal [' . self::$acceptSignals[$signal] . '] received ... prepare shutdown');

        $instances = Pool::instances();
        foreach ($instances as $nsqdIns) {
            if ($nsqdIns->isConsumer()) {
                $nsqdIns->closing();
            }
        }
    }
}
