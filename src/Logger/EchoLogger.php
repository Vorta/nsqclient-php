<?php

namespace NSQClient\Logger;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;

/**
 * Class EchoLogger
 * @package NSQClient\Logger
 */
class EchoLogger extends AbstractLogger
{
    /**
     * @var string[]
     */
    private array $levels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @var array<string, string>
     */
    private array $colors = [
        LogLevel::EMERGENCY => '0;31m', // red
        LogLevel::ALERT => '0;31m', // red
        LogLevel::CRITICAL => '0;31m', // red
        LogLevel::ERROR => '0;31m', // red
        LogLevel::WARNING => '1;33m', // yellow
        LogLevel::NOTICE => '0;35m', // purple
        LogLevel::INFO => '0;36m', // cyan
        LogLevel::DEBUG => '0;32m', // green
    ];

    /**
     * @var string
     */
    private string $colorCtxKey = '0;37m'; // light gray

    /**
     * @var string
     */
    private string $colorMsg = '1;37m'; // white

    /**
     * @var string
     */
    private string $colorNO = "\033[0m";

    /**
     * @var string
     */
    private string $colorBGN = "\033[";

    /**
     * @var string[]
     */
    private array $allows = [];

    /**
     * EchoLogger constructor.
     * @param string $minimalLevel
     */
    public function __construct(string $minimalLevel = LogLevel::NOTICE)
    {
        $this->allows = array_slice(
            $this->levels,
            0,
            array_search($minimalLevel, $this->levels, true) + 1
        );
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
        if (in_array($level, $this->allows)) {
            printf(
                '[%s]%s[%s] : %s ~ %s %s',
                $this->printableLevel($level),
                " ",
                date('Y-m-d H:i:s'),
                $this->printableMessage($message),
                $this->printableContext($context),
                "\n"
            );
        }
    }

    /**
     * @param string $level
     * @return string
     */
    private function printableLevel(string $level): string
    {
        return $this->colorBGN . $this->colors[$level] . strtoupper($level) . $this->colorNO;
    }

    /**
     * @param string $message
     * @return string
     */
    private function printableMessage(string $message): string
    {
        return $this->colorBGN . $this->colorMsg . $message . $this->colorNO;
    }

    /**
     * @param array<string, mixed> $context
     * @return string
     */
    private function printableContext(array $context): string
    {
        $print = '[';

        array_walk($context, function ($item, $key) use (&$print) {
            $ctx = $this->colorBGN . $this->colorCtxKey . $key . $this->colorNO . '=';
            if (is_array($item)) {
                $ctx .= json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $ctx .= $item;
            }
            $print .= $ctx . ',';
        });

        return $print . ']';
    }
}
