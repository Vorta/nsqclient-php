<?php

namespace NSQClient\Logger;

use NSQClient\SDK;
use Psr\Log\NullLogger;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;

/**
 * Class Logger
 * @package NSQClient\Logger
 */
class Logger extends AbstractLogger
{
    /**
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * @var NullLogger
     */
    private $nullLogger = null;

    /**
     * Logger constructor.
     */
    private function __construct()
    {
        $this->nullLogger = new NullLogger();
    }

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param mixed  $level
     * @param string $message
     * @param array<mixed>  $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = []): void
    {
        if (SDK::$presentLogger) {
            SDK::$presentLogger->log($level, $message, $context);
        } else {
            $this->nullLogger->log($level, $message, $context);
        }
    }
}
