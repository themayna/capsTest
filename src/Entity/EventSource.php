<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventSourceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventSourceRepository::class)]
class EventSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Source::class, inversedBy: 'eventSources')]
    #[ORM\JoinColumn(nullable: false)]
    private Source $source;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(type: 'json')]
    private array $config = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function setSource(Source $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }
}
