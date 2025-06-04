<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Middleware;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * @internal
 */
#[Package('framework')]
class QueuedTimeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // add a SentAtStamp if the envelope does not have one and is not in the receive phase
        if ($envelope->last(SentAtStamp::class) === null && $envelope->last(ReceivedStamp::class) === null) {
            $now = new \DateTimeImmutable('@' . time());
            $envelope = $envelope->with(new SentAtStamp($now));

            $this->logger->error('MessageStats: Added SentAtStamp to message', [
                'message_class' => \get_class($envelope->getMessage()),
                'sent_at' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
