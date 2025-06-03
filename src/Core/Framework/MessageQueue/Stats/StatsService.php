<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
class StatsService
{
    public function __construct(
        private readonly AbstractStatsRepository $statsRepository,
        private readonly bool $enabled,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getStats(): MessageStatsResponseEntity
    {
        if (!$this->enabled) {
            return new MessageStatsResponseEntity(enabled: false);
        }

        return new MessageStatsResponseEntity(
            enabled: true,
            stats: $this->statsRepository->getStats()
        );
    }

    public function registerMessage(Envelope $envelope): void
    {
        $this->logger->warning('Starting to register message', [
            'message_class' => \get_class($envelope->getMessage()),
        ]);

        if (!$this->enabled) {
            $this->logger->warning('Message registration skipped - stats service is disabled');

            return;
        }

        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            $this->logger->warning('Message registration skipped - missing SentAtStamp');

            return;
        }

        $timeInQueue = time() - $sentAtStamp->getSentAt()->getTimestamp();
        $messageFqcn = \get_class($envelope->getMessage());
        $this->statsRepository->updateMessageStats($messageFqcn, $timeInQueue);

        $this->logger->warning('Message registration completed successfully', [
            'message_class' => $messageFqcn,
            'time_in_queue' => $timeInQueue,
        ]);
    }
}
