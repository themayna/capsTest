<?php

declare(strict_types=1);

namespace App\src\Entity;

use App\src\Repository\SourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
class Source
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private string $identifier;

    #[ORM\Column(type: 'bigint')]
    private int $lastEventId = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: EventSource::class, mappedBy: 'source')]
    private Collection $eventSources;

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'source')]
    private Collection $events;

    public function __construct()
    {
        $this->eventSources = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getLastEventId(): int
    {
        return $this->lastEventId;
    }

    public function setLastEventId(int $lastEventId): self
    {
        $this->lastEventId = $lastEventId;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, EventSource>
     */
    public function getEventSources(): Collection
    {
        return $this->eventSources;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }
}
