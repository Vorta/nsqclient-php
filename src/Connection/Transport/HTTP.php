<?php

namespace NSQClient\Connection\Transport;

use NSQClient\SDK;

/**
 * Class HTTP
 * @package NSQClient\Connection\Transport
 */
class HTTP
{
    /**
     * @var string
     */
    private static string $agent = SDK::NAME . '-' . SDK::VERSION;

    /**
     * @var string[]
     */
    private static array $headers = ['Accept: application/vnd.nsq; version=1.0'];

    /**
     * @var string
     */
    private static string $encoding = '';

    /**
     * @param string $url
     * @param array<int, mixed> $extOptions
     * @return array<int, mixed>
     */
    public static function get(string $url, array $extOptions = []): array
    {
        return self::request($url, [], $extOptions);
    }

    /**
     * @param string $url
     * @param string $data
     * @param array<int, mixed> $extOptions
     * @return array<int, mixed>
     */
    public static function post(string $url, string $data, array $extOptions = []): array
    {
        return self::request($url, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data], $extOptions);
    }

    /**
     * @param string $url
     * @param array<int, mixed> $selfOptions
     * @param array<int, mixed> $usrOptions
     * @return array<int, mixed>
     */
    private static function request(string $url, array $selfOptions, array $usrOptions): array
    {
        $ch = curl_init();

        $initOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING       => self::$encoding,
            CURLOPT_USERAGENT      => self::$agent,
            CURLOPT_HTTPHEADER     => self::$headers,
            CURLOPT_FAILONERROR    => true
        ];

        count($selfOptions) && $initOptions = self::mergeOptions($initOptions, $selfOptions);
        count($usrOptions) && $initOptions = self::mergeOptions($initOptions, $usrOptions);

        curl_setopt_array($ch, $initOptions);

        $result = curl_exec($ch);

        $error = curl_errno($ch) ? [curl_errno($ch), curl_error($ch)] : null;

        curl_close($ch);

        return [$error, $result];
    }

    /**
     * @param array<int, mixed> $base
     * @param array<int, mixed> $custom
     * @return array<int, mixed>
     */
    private static function mergeOptions(array $base, array $custom): array
    {
        foreach ($custom as $key => $val) {
            $base[$key] = $val;
        }
        return $base;
    }
}
