<?php

namespace NSQClient\Protocol;

/**
 * Class Command
 * @package NSQClient\Protocol
 */
class Command
{
    /**
     * Magic header
     */
    private const MAGIC_V2 = '  V2';

    /**
     * Magic hello
     * @return string
     */
    public static function magic(): string
    {
        return self::MAGIC_V2;
    }

    /**
     * Identify self [IDENTIFY]
     * @param string $clientId
     * @param string $hostname
     * @param string $userAgent
     * @return string
     */
    public static function identify(string $clientId, string $hostname, string $userAgent): string
    {
        $cmd = self::command('IDENTIFY');
        /** @var string $data */
        $data = json_encode([
            'client_id'     => $clientId,
            'hostname'      => $hostname,
            'user_agent'    => $userAgent
        ]);
        $size = pack('N', strlen($data));
        return $cmd . $size . $data;
    }

    /**
     * Subscribe [SUB]
     * @param string $topic
     * @param string $channel
     * @return string
     */
    public static function subscribe(string $topic, string $channel): string
    {
        return self::command('SUB', $topic, $channel);
    }

    /**
     * Publish [PUB]
     * @param string $topic
     * @param string $message
     * @param int $deferred
     * @return string
     */
    public static function message(string $topic, string $message, ?int $deferred = null): string
    {
        $cmd = is_null($deferred)
            ? self::command('PUB', $topic)
            : self::command('DPUB', $topic, $deferred);
        $data = Binary::packString($message);
        $size = pack('N', strlen($data));
        return $cmd . $size . $data;
    }

    /**
     * Publish -multi [MPUB]
     * @param string $topic
     * @param string[] $messages
     * @return string
     */
    public static function messages(string $topic, array $messages): string
    {
        $cmd = self::command('MPUB', $topic);
        $msgNum = pack('N', count($messages));
        $buffer = '';
        foreach ($messages as $message) {
            $data = Binary::packString($message);
            $size = pack('N', strlen($data));
            $buffer .= $size . $data;
        }
        $bodySize = pack('N', strlen($msgNum . $buffer));
        return $cmd . $bodySize . $msgNum . $buffer;
    }

    /**
     * Ready [RDY]
     * @param int $count
     * @return string
     */
    public static function ready(int $count): string
    {
        return self::command('RDY', $count);
    }

    /**
     * Finish [FIN]
     * @param string $id
     * @return string
     */
    public static function finish($id): string
    {
        return self::command('FIN', $id);
    }

    /**
     * Requeue [REQ]
     * @param string $id
     * @param int $millisecond
     * @return string
     */
    public static function requeue(string $id, int $millisecond): string
    {
        return self::command('REQ', $id, $millisecond);
    }

    /**
     * No-op [NOP]
     * @return string
     */
    public static function nop(): string
    {
        return self::command('NOP');
    }

    /**
     * Cleanly close [CLS]
     * @return string
     */
    public static function close(): string
    {
        return self::command('CLS');
    }

    /**
     * Gen command
     * @return string
     */
    private static function command(): string
    {
        $args = func_get_args();
        $cmd = array_shift($args);
        return sprintf('%s %s%s', $cmd, implode(' ', $args), "\n");
    }
}
