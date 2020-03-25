<?php

namespace NSQClient;

use Exception;
use NSQClient\Access\Endpoint;
use NSQClient\Connection\Lookupd;
use NSQClient\Connection\Nsqd;
use NSQClient\Connection\Pool;
use NSQClient\Logger\Logger;
use NSQClient\Message\Message;

/**
 * Class Queue
 * @package NSQClient
 */
class Queue
{
    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @param Message $message
     * @return bool
     */
    public static function publish(
        Endpoint $endpoint,
        string $topic,
        Message $message
    ): bool {
        $routes = Lookupd::getNodes($endpoint, $topic);
        $route = $routes[rand(0, count($routes) - 1)];
        $keys = [$route['host'], $route['ports']['tcp']];

        return Pool::register($keys, function () use ($endpoint, $route) {
            Logger::getInstance()->info('Creating new nsqd for producer', [
                'lookupd' => $endpoint->getLookupd(),
                'route' => $route
            ]);
            return (new Nsqd($endpoint))
                ->setRoute($route)
                ->setLifecycle(SDK::$pubRecyclingSec)
                ->setProducer();
        })
        ->setTopic($topic)
        ->publish($message);
    }

    /**
     * @param Endpoint $endpoint
     * @param string $topic
     * @param string $channel
     * @param callable $processor
     * @param int $lifecycle
     * @throws Exception
     */
    public static function subscribe(
        Endpoint $endpoint,
        string $topic,
        string $channel,
        callable $processor,
        int $lifecycle = 0
    ): void {
        $routes = Lookupd::getNodes($endpoint, $topic);

        foreach ($routes as $route) {
            $keys = [$route['topic'], $route['host'], $route['ports']['tcp']];

            Pool::register($keys, function () use ($endpoint, $route, $topic, $processor, $lifecycle) {
                Logger::getInstance()->info('Creating new nsqd for consumer', [
                    'lookupd' => $endpoint->getLookupd(),
                    'route' => $route,
                    'topic' => $topic,
                    'lifecycle' => $lifecycle
                ]);

                return (new Nsqd($endpoint))
                    ->setRoute($route)
                    ->setTopic($topic)
                    ->setLifecycle($lifecycle)
                    ->setConsumer($processor);
            })->subscribe($channel);
        }

        Pool::getEvLoop()->run();
    }
}
