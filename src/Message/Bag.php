<?php

namespace NSQClient\Message;

/**
 * Class Bag
 * @package NSQClient\Message
 */
class Bag
{
    /**
     * @var Message[]
     */
    private array $messages = [];

    /**
     * @param array<mixed> $list
     * @return self
     */
    public static function generate($list): self
    {
        $bag = new self();
        foreach ($list as $item) {
            $bag->append(new Message($item));
        }
        return $bag;
    }

    /**
     * @param Message $message
     * @return self
     */
    public function append(Message $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function export(): array
    {
        $bag = [];
        foreach ($this->messages as $msg) {
            $bag[] = $msg->data();
        }
        return $bag;
    }
}
