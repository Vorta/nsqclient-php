<?php

namespace NSQClient\Protocol;

/**
 * Class CommandHTTP
 * @package NSQClient\Protocol
 */
class CommandHTTP
{
    /**
     * Publish [PUB]
     * @param string $topic
     * @param string $message
     * @param int|null $deferred
     * @return array<int, mixed>
     */
    public static function message(string $topic, string $message, ?int $deferred = null): array
    {
        if ($deferred === null) {
            $url = sprintf('pub?topic=%s', $topic);
        } else {
            $url = sprintf('pub?topic=%s&defer=%s', $topic, $deferred);
        }

        return [
            $url,
            Binary::packString($message)
        ];
    }

    /**
     * Publish -multi [MPUB]
     * @param string $topic
     * @param string[] $messages
     * @return array<int, mixed>
     */
    public static function messages(string $topic, array $messages)
    {
        $buffer = '';
        foreach ($messages as $message) {
            $data = Binary::packString($message);
            $size = pack('N', strlen($data));
            $buffer .= $size . $data;
        }

        return [
            sprintf('mpub?topic=%s&binary=true', $topic),
            pack('N', count($messages)) . $buffer
        ];
    }
}
