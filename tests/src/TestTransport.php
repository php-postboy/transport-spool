<?php

declare(strict_types=1);

namespace Staff\Postboy\TransportSpool;

use Postboy\Contract\Message\MessageInterface;
use Postboy\Contract\Transport\Exception\TransportException;
use Postboy\Contract\Transport\TransportInterface;

class TestTransport implements TransportInterface
{
    /**
     * @var MessageInterface[][]
     */
    private array $messages = [];

    /**
     * @inheritDoc
     */
    public function send(MessageInterface $message): void
    {
        $successTry = (int)$message->getHeader('X-Success-Try');
        $currentTry = (int)$message->getHeader('X-Current-Try');
        if ($currentTry < $successTry) {
            $message->setHeader('X-Current-Try', (string)($currentTry + 1));
            throw new TransportException('message not sent');
        }
        $this->messages[$currentTry][] = $message;
    }

    public function pull(int $try): ?MessageInterface
    {
        if (!array_key_exists($try, $this->messages) || empty($this->messages[$try])) {
            return null;
        }
        return array_shift($this->messages[$try]);
    }
}
