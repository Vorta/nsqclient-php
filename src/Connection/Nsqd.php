<?php

namespace NSQClient\Connection;

use Exception;
use NSQClient\Access\Endpoint;
use NSQClient\Connection\Transport\HTTP;
use NSQClient\Connection\Transport\TCP;
use NSQClient\Contract\Network\Stream;
use NSQClient\Exception\GenericErrorException;
use NSQClient\Exception\InvalidMessageException;
use NSQClient\Exception\NetworkSocketException;
use NSQClient\Exception\UnknownProtocolException;
use NSQClient\Logger\Logger;
use NSQClient\Message\Bag as MessageBag;
use NSQClient\Message\Message;
use NSQClient\Protocol\Command;
use NSQClient\Protocol\CommandHTTP;
use NSQClient\Protocol\Specification;
use NSQClient\SDK;

/**
 * Class Nsqd
 * @package NSQClient\Connection
 */
class Nsqd
{
    /**
     * @var Endpoint|null
     */
    private ?Endpoint $endpoint = null;

    /**
     * @var string
     */
    private string $host = '127.0.0.1';

    /**
     * @var int
     */
    private int $portTCP = 4150;

    /**
     * @var TCP|null
     */
    private ?TCP $connTCP = null;

    /**
     * @var int
     */
    private int $portHTTP = 4151;

    /**
     * @var string
     */
    private string $topic = 'topic';

    /**
     * PUB: Idle seconds before recycling
     * SUB: Run seconds before exiting
     * @var int
     */
    private int $lifecycle = 0;

    /**
     * @var callable|null
     */
    private $subProcessor = null;

    /**
     * Nsqd constructor.
     * @param Endpoint $endpoint
     */
    public function __construct(Endpoint $endpoint)
    {
        $this->endpoint = $endpoint;

        if ($this->endpoint->getConnType() === 'tcp') {
            $this->connTCP = new TCP();
            $this->connTCP->setHandshake([$this, 'handshake']);
        }
    }

    /**
     * @param array<string, mixed> $route
     * @return self
     */
    public function setRoute(array $route): self
    {
        $this->host = $route['host'];
        $this->portTCP = $route['ports']['tcp'];
        $this->portHTTP = $route['ports']['http'];

        if ($this->connTCP) {
            $this->connTCP->setTarget($this->host, $this->portTCP);
        }

        return $this;
    }

    /**
     * @param string $topic
     * @return self
     */
    public function setTopic(string $topic): self
    {
        $this->topic = $topic;
        return $this;
    }

    /**
     * @param int $seconds
     * @return self
     */
    public function setLifecycle(int $seconds): self
    {
        $this->lifecycle = $seconds;
        return $this;
    }

    /**
     * @return self
     */
    public function setProducer(): self
    {
        if ($this->connTCP) {
            $this->connTCP->setRecycling($this->lifecycle);
        }

        return $this;
    }

    /**
     * @param callable $processor
     * @return self
     */
    public function setConsumer(callable $processor): self
    {
        $this->subProcessor = $processor;

        if ($this->lifecycle) {
            $nsqd = $this;
            Pool::getEvLoop()->addTimer($this->lifecycle, function () use ($nsqd) {
                $nsqd->closing();
            });
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getSockID(): int
    {
        return (int) $this->connTCP->socket();
    }

    /**
     * @return Stream
     */
    public function getSockIns(): Stream
    {
        return $this->connTCP;
    }

    /**
     * @return bool
     */
    public function isConsumer(): bool
    {
        return !is_null($this->subProcessor);
    }

    /**
     * @param Stream $stream
     */
    public function handshake(Stream $stream): void
    {
        $stream->write(Command::magic());
    }

    /**
     * @param Message|MessageBag $message
     * @return bool
     */
    public function publish($message): bool
    {
        return
            $this->endpoint->getConnType() === 'tcp'
                ? $this->publishViaTCP($message)
                : $this->publishViaHTTP($message)
        ;
    }

    /**
     * @param string $channel
     * @throws Exception
     */
    public function subscribe(string $channel): void
    {
        $this->connTCP->setBlocking(false);

        $evLoop = Pool::getEvLoop();

        $evLoop->addReadStream(
            $this->connTCP->socket(),
            fn ($socket) => $this->dispatching(Specification::readFrame(Pool::search($socket)))
        );

        $this->connTCP->write(Command::identify(
            (string) getmypid(),
            (string) gethostname(),
            sprintf('%s/%s', SDK::NAME, SDK::VERSION)
        ));
        $this->connTCP->write(Command::subscribe($this->topic, $channel));
        $this->connTCP->write(Command::ready(1));

        Pool::setEvAttached();

        Logger::getInstance()->debug('Consumer is ready', $this->loggingMeta());
    }

    /**
     * @param string $messageID
     */
    public function finish(string $messageID): void
    {
        Logger::getInstance()->debug('Make message is finished', $this->loggingMeta(['id' => $messageID]));
        $this->connTCP->write(Command::finish($messageID));
    }

    /**
     * @param string $messageID
     * @param int $millisecond
     */
    public function requeue(string $messageID, int $millisecond): void
    {
        Logger::getInstance()->debug(
            'Make message is requeued',
            $this->loggingMeta(['id' => $messageID, 'delay' => $millisecond])
        );
        $this->connTCP->write(Command::requeue($messageID, $millisecond));
    }

    /**
     * subscribe closing
     */
    public function closing(): void
    {
        Logger::getInstance()->info('Consumer is closing', $this->loggingMeta());
        $this->connTCP->write(Command::close());
    }

    /**
     * process exiting
     */
    private function exiting(): void
    {
        Logger::getInstance()->info('Consumer is exiting', $this->loggingMeta());
        $this->connTCP->close();
        Pool::setEvDetached();
    }

    /**
     * @param Message|MessageBag $message
     * @return bool
     */
    private function publishViaHTTP($message): bool
    {
        if ($message instanceof Message) {
            list($uri, $data) = CommandHTTP::message($this->topic, $message->data());
        } elseif ($message instanceof MessageBag) {
            list($uri, $data) = CommandHTTP::messages($this->topic, $message->export());
        } else {
            Logger::getInstance()->error(
                'Un-expected pub message',
                $this->loggingMeta(['input' => json_encode($message)])
            );
            throw new InvalidMessageException('Unknowns message object');
        }

        list($error, $result) = HTTP::post(
            sprintf('http://%s:%d/%s', $this->host, $this->portHTTP, $uri),
            $data
        );

        if ($error) {
            list($netErrNo, $netErrMsg) = $error;
            Logger::getInstance()->error(
                'HTTP Publish is failed',
                $this->loggingMeta(['no' => $netErrNo, 'msg' => $netErrMsg])
            );
            throw new NetworkSocketException($netErrMsg, $netErrNo);
        } else {
            return $result === 'OK';
        }
    }

    /**
     * @param Message|MessageBag $message
     * @return bool
     */
    private function publishViaTCP($message): bool
    {
        if ($message instanceof Message) {
            $buffer = Command::message($this->topic, $message->data(), $message->deferred());
        } elseif ($message instanceof MessageBag) {
            $buffer = Command::messages($this->topic, $message->export());
        } else {
            Logger::getInstance()->error(
                'Un-expected pub message',
                $this->loggingMeta(['input' => json_encode($message)])
            );
            throw new InvalidMessageException('Unknowns message object');
        }

        $this->connTCP->write($buffer);

        do {
            $result = $this->dispatching(Specification::readFrame($this->connTCP));
        } while (is_null($result));

        return $result;
    }

    /**
     * @param array<string, mixed> $frame
     * @return bool|null
     */
    private function dispatching(array $frame): ?bool
    {
        switch (true) {
            case Specification::frameIsOK($frame):
                return true;
            case Specification::frameIsMessage($frame):
                Logger::getInstance()->debug(
                    'FRAME got is message',
                    $this->loggingMeta(['id' => $frame['id'], 'data' => $frame['payload']])
                );
                $this->processingMessage(
                    new Message(
                        $frame['payload'],
                        $frame['id'],
                        $frame['attempts'],
                        $frame['timestamp'],
                        $this
                    )
                );
                return null;
            case Specification::frameIsHeartbeat($frame):
                Logger::getInstance()->debug('FRAME got is heartbeat', $this->loggingMeta());
                $this->connTCP->write(Command::nop());
                return null;
            case Specification::frameIsError($frame):
                Logger::getInstance()->error('FRAME got is error', $this->loggingMeta(['error' => $frame['error']]));
                throw new GenericErrorException($frame['error']);
            case Specification::frameIsBroken($frame):
                Logger::getInstance()->warning('FRAME got is broken', $this->loggingMeta(['error' => $frame['error']]));
                throw new GenericErrorException($frame['error']);
            case Specification::frameIsCloseWait($frame):
                Logger::getInstance()->debug('FRAME got is close-wait', $this->loggingMeta());
                $this->exiting();
                return null;
            default:
                Logger::getInstance()->warning('FRAME got is unknowns', $this->loggingMeta());
                throw new UnknownProtocolException('Unknowns protocol data (' . json_encode($frame) . ')');
        }
    }

    /**
     * @param Message $message
     */
    private function processingMessage(Message $message): void
    {
        try {
            call_user_func_array($this->subProcessor, [$message]);
        } catch (Exception $exception) {
            // TODO add observer for usr callback
            Logger::getInstance()->critical('Consuming processor has exception', $this->loggingMeta([
                'cls' => get_class($exception),
                'msg' => $exception->getMessage()
            ]));
        }
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function loggingMeta(array $extra = []): array
    {
        return array_merge([
            'topic' => $this->topic,
            'host' => $this->host,
            'port-tcp' => $this->portTCP
        ], $extra);
    }
}
