<?php

declare(strict_types=1);

namespace App\src\Service;

use App\src\Contract\EventStorageInterface;
use App\src\Entity\Event;
use App\src\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;

final class EventStorage implements EventStorageInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function save(string $sourceIdentifier, array $events, int $lastEventId): void
    {
        $source = $this->entityManager->find(Source::class, $sourceIdentifier);

        if (!$source instanceof Source) {
            throw new \InvalidArgumentException("Source '{$sourceIdentifier}' does not exist");
        }

        /** @var \App\DTO\Event $eventDto */
        foreach ($events as $eventDto) {
            $event = (new Event())
                ->setSource($source)
                ->setSourceEventId($eventDto->getId())
                ->setType($eventDto->getType())
                ->setPayload($eventDto->getPayload())
                ->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($event);
        }

        $source->setLastEventId($lastEventId);

        $this->entityManager->flush();
    }

    public function getLastEventId(string $sourceIdentifier): int
    {
        $source = $this->entityManager->find(Source::class, $sourceIdentifier);

        if (!$source instanceof Source) {
            throw new \InvalidArgumentException("Source '{$sourceIdentifier}' does not exist");
        }

        return $source->getLastEventId();
    }
}
