<?php

namespace App\Entity;

use App\Repository\MechanicMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MechanicMappingRepository::class)]
#[ORM\Table(name: 'mechanic_mapping')]
class MechanicMapping implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $bggMechanic;

    #[ORM\Column(length: 100)]
    private string $engelsteinFamily;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    public function jsonSerialize(): array
    {
        return [
            'id'               => $this->id,
            'bggMechanic'      => $this->bggMechanic,
            'engelsteinFamily' => $this->engelsteinFamily,
            'description'      => $this->description,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getBggMechanic(): string { return $this->bggMechanic; }
    public function setBggMechanic(string $v): static { $this->bggMechanic = $v; return $this; }
    public function getEngelsteinFamily(): string { return $this->engelsteinFamily; }
    public function setEngelsteinFamily(string $v): static { $this->engelsteinFamily = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
}
