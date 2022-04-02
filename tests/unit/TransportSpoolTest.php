<?php

declare(strict_types=1);

namespace Tests\Postboy\TransportSpool;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Postboy\Contract\Message\Body\AttachmentInterface;
use Postboy\Contract\Message\Body\BodyInterface;
use Postboy\Contract\Message\Body\BodyPartInterface;
use Postboy\Contract\Message\Body\Collection\BodyCollectionInterface;
use Postboy\Contract\Message\Body\ContentInterface;
use Postboy\Contract\Message\Body\MultipartBodyInterface;
use Postboy\Contract\Message\Body\Stream\StreamInterface;
use Postboy\Contract\Message\MessageInterface;
use Postboy\Message\Body\BodyPart;
use Postboy\Message\Body\Stream\StringStream;
use Postboy\Message\Message;
use Postboy\SpoolMemory\SpoolMemory;
use Postboy\TransportSpool\TransportSpool;
use Staff\Postboy\TransportSpool\TestTransport;

class TransportSpoolTest extends TestCase
{
    public function testPushAndPool()
    {
        $mainTransport = new TestTransport();
        $transport = new TransportSpool(new SpoolMemory(), $mainTransport);

        $messages = [];
        for ($number = 1; $number <= 100; $number++) {
            $successTry = rand(1, 5);
            $text = sprintf('body-%d.%d', $number, $successTry);
            $subject = sprintf('subject-%d.%d', $number, $successTry);
            $messages[$successTry][] = $this->createMessage($text, $subject, $successTry);
        }

        foreach ($messages as $list) {
            foreach ($list as $message) {
                $transport->send($message);
            }
        }
        $transport->flush();

        foreach ($messages as $try => $list) {
            foreach ($list as $message) {
                $this->assertMessage($message, $mainTransport->pull($try));
            }
            Assert::assertNull($mainTransport->pull($try));
        }
    }

    private function createMessage(string $text, string $subject, int $successTry): MessageInterface
    {
        $body = new BodyPart(new StringStream($text), 'text/plain');
        $message = new Message($body, $subject);
        $message->setHeader('X-Success-Try', (string)$successTry);
        $message->setHeader('X-Current-Try', '1');
        return $message;
    }

    private function assertMessage(MessageInterface $expected, MessageInterface $actual): void
    {
        Assert::assertSame($expected->getHeader('subject'), $actual->getHeader('subject'));
        $this->assertBody($expected->getBody(), $actual->getBody());
    }

    private function assertBody(BodyInterface $expected, BodyInterface $actual): void
    {
        Assert::assertSame($expected->getContentType(), $actual->getContentType());
        if ($expected instanceof AttachmentInterface) {
            Assert::assertInstanceOf(AttachmentInterface::class, $actual);
            Assert::assertSame((string)$expected->getFilename(), (string)$actual->getFilename());
        }
        if ($expected instanceof ContentInterface) {
            Assert::assertInstanceOf(ContentInterface::class, $actual);
            Assert::assertSame((string)$expected->getContentId(), (string)$actual->getContentId());
        }
        if ($expected instanceof BodyPartInterface) {
            Assert::assertInstanceOf(BodyPartInterface::class, $actual);
            $this->assertStream($expected->getStream(), $actual->getStream());
        }

        if ($expected instanceof MultipartBodyInterface) {
            Assert::assertInstanceOf(MultipartBodyInterface::class, $actual);
            Assert::assertSame($expected->getBoundary(), $actual->getBoundary());
            $this->assertBodyCollection($expected->getParts(), $actual->getParts());
        }
    }

    private function assertBodyCollection(BodyCollectionInterface $expected, BodyCollectionInterface $actual): void
    {
        Assert::assertSame($expected->count(), $actual->count());
        foreach ($expected as $expectedPart) {
            $actualPart = $actual->current();
            $actual->next();
            $this->assertBody($expectedPart, $actualPart);
        }
    }

    private function assertStream(StreamInterface $expected, StreamInterface $actual): void
    {
        Assert::assertSame($this->readStream($expected), $this->readStream($actual));
    }

    private function readStream(StreamInterface $stream): string
    {
        $result = '';
        $stream->rewind();
        while (!$stream->eof()) {
            $result .= $stream->read(4096);
        }
        return $result;
    }
}
