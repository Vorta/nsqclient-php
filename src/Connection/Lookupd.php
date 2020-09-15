<?php

namespace NSQClient\Connection;

use NSQClient\Access\Endpoint;
use NSQClient\Connection\Transport\HTTP;
use NSQClient\Exception\LookupTopicException;
use NSQClient\Logger\Logger;

/**
 * Class Lookupd
 * @package NSQClient\Connection
 */
class Lookupd
{
    /**
     * @var string
     */
    private static string $queryFormat = '/lookup?topic=%s';

    /**
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     */
    private static array $cache = [];

    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @return bool
     * @throws LookupTopicException
     */
    public static function topicExists(Endpoint $endpoint, string $topic): bool
    {
        list($error, $response) = HTTP::get($endpoint->getLookupd() . '/topics');

        if ($error) {
            list($netErrNo, $netErrMsg) = $error;
            Logger::getInstance()->error('Unable to get topics', ['no' => $netErrNo, 'msg' => $netErrMsg]);
            throw new LookupTopicException($netErrMsg, $netErrNo);
        } else {
            Logger::getInstance()->debug('Topics check got', ['raw' => $response]);
            $topics = json_decode($response, true);
            $topics = $topics['topics'] ?? [];
        }

        return in_array($topic, $topics);
    }

    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @return array<int, array<string, mixed>>
     * @throws LookupTopicException
     */
    public static function getNodes(Endpoint $endpoint, string $topic): array
    {
        if (isset(self::$cache[$endpoint->getUniqueID()][$topic])) {
            return self::$cache[$endpoint->getUniqueID()][$topic];
        }

        $url = $endpoint->getLookupd() . sprintf(self::$queryFormat, $topic);

        list($error, $result) = HTTP::get($url);

        if ($error) {
            list($netErrNo, $netErrMsg) = $error;
            Logger::getInstance()->error('Lookupd request failed', ['no' => $netErrNo, 'msg' => $netErrMsg]);
            throw new LookupTopicException($netErrMsg, $netErrNo);
        } else {
            Logger::getInstance()->debug('Lookupd results got', ['raw' => $result]);
            return self::$cache[$endpoint->getUniqueID()][$topic] = self::parseResult($result, $topic);
        }
    }

    /**
     * @param string $rawJson
     * @param string $scopeTopic
     * @return array<int, array<string, mixed>>
     */
    private static function parseResult(string $rawJson, string $scopeTopic): array
    {
        $result = json_decode($rawJson, true);

        $nodes = [];

        if (isset($result['producers'])) {
            foreach ($result['producers'] as $producer) {
                $nodes[] = [
                    'topic' => $scopeTopic,
                    'host' => $producer['broadcast_address'],
                    'ports' => [
                        'tcp' => $producer['tcp_port'],
                        'http' => $producer['http_port']
                    ]
                ];
            }
        }

        return $nodes;
    }
}
