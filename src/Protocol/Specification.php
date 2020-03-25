<?php

namespace NSQClient\Protocol;

use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\UnknownFrameException;

/**
 * Class Specification
 * @package NSQClient\Protocol
 */
class Specification
{
    /**
     * Frame types
     */
    private const FRAME_TYPE_BROKEN = -1;
    private const FRAME_TYPE_RESPONSE = 0;
    private const FRAME_TYPE_ERROR = 1;
    private const FRAME_TYPE_MESSAGE = 2;

    /**
     * Heartbeat response content
     */
    private const HEARTBEAT = '_heartbeat_';

    /**
     * OK response content
     */
    private const OK = 'OK';

    /**
     * CLOSE_WAIT response content
     */
    private const CLOSE_WAIT = 'CLOSE_WAIT';

    /**
     * Read frame
     * @param Stream $buffer
     * @return array<string, mixed>
     */
    public static function readFrame(Stream $buffer): array
    {
        $size = Binary::readInt($buffer);
        $frameType = Binary::readInt($buffer);

        $frame = ['type' => $frameType, 'size'  => $size];

        // switch
        switch ($frameType) {
            case self::FRAME_TYPE_RESPONSE:
                $frame['response'] = Binary::readString($buffer, $size - 4);
                break;
            case self::FRAME_TYPE_ERROR:
                $frame['error'] = Binary::readString($buffer, $size - 4);
                break;
            case self::FRAME_TYPE_MESSAGE:
                $frame['timestamp'] = Binary::readLong($buffer);
                $frame['attempts'] = Binary::readShort($buffer);
                $frame['id'] = Binary::readString($buffer, 16);
                $frame['payload'] = Binary::readString($buffer, $size - 30);
                break;
            default:
                throw new UnknownFrameException(Binary::readString($buffer, $size - 4));
        }

        // check frame data
        foreach ($frame as $k => $val) {
            if (is_null($val)) {
                $frame['type'] = self::FRAME_TYPE_BROKEN;
                $frame['error'] = 'broken frame (maybe network error)';
                break;
            }
        }

        return $frame;
    }

    /**
     * Test if frame is a message
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsMessage(array $frame): bool
    {
        return isset($frame['type'], $frame['payload']) && $frame['type'] === self::FRAME_TYPE_MESSAGE;
    }

    /**
     * Test if frame is HEARTBEAT
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsHeartbeat(array $frame): bool
    {
        return self::frameIsResponse($frame, self::HEARTBEAT);
    }

    /**
     * Test if frame is OK
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsOK(array $frame): bool
    {
        return self::frameIsResponse($frame, self::OK);
    }

    /**
     * Test if frame is CLOSE_WAIT
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsCloseWait(array $frame): bool
    {
        return self::frameIsResponse($frame, self::CLOSE_WAIT);
    }

    /**
     * Test if frame is ERROR
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsError(array $frame): bool
    {
        return isset($frame['type']) && $frame['type'] === self::FRAME_TYPE_ERROR && isset($frame['error']);
    }

    /**
     * Test if frame is BROKEN
     * @param array<string, mixed> $frame
     * @return bool
     */
    public static function frameIsBroken(array $frame): bool
    {
        return isset($frame['type']) && $frame['type'] === self::FRAME_TYPE_BROKEN;
    }

    /**
     * Test if frame is a response frame (optionally with content $response)
     * @param array<string, mixed> $frame
     * @param string|null $response
     * @return bool
     */
    private static function frameIsResponse(array $frame, ?string $response = null): bool
    {
        return
            isset($frame['type'], $frame['response'])
            && $frame['type'] === self::FRAME_TYPE_RESPONSE
            && ($response === null || $frame['response'] === $response);
    }
}
