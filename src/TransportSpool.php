<?php

declare(strict_types=1);

namespace Postboy\TransportSpool;

use Postboy\Contract\Message\MessageInterface;
use Postboy\Contract\Spool\SpoolInterface;
use Postboy\Contract\Transport\Exception\TransportException;
use Postboy\Contract\Transport\TransportInterface;

class TransportSpool implements TransportInterface
{
    /**
     * @var SpoolInterface
     */
    private SpoolInterface $spool;

    /**
     * @var TransportInterface
     */
    private TransportInterface $transport;

    private int $tries;

    public function __construct(SpoolInterface $spool, TransportInterface $transport, int $tries = 5)
    {
        $this->spool = $spool;
        $this->transport = $transport;
        $this->tries = $tries > 0 ? $tries : 1;
    }

    /**
     * @inheritDoc
     */
    public function send(MessageInterface $message): void
    {
        $this->spool->push($message, 1);
    }

    public function flush(): void
    {
        for ($queue = 1; $queue <= $this->tries; $queue++) {
            do {
                $message = $this->spool->pull($queue);
                $this->handleMessageFromSpool($message, $queue);
            } while (!is_null($message));
        }
    }

    public function handleMessageFromSpool(?MessageInterface $message, int $queue): void
    {
        if (is_null($message)) {
            return;
        }
        try {
            $this->transport->send($message);
        } catch (TransportException $e) {
            if ($queue < $this->tries) {
                $this->spool->push($message, $queue + 1);
            }
        }
    }
}
