<?php

namespace App\Entity;

use App\Repository\ThemeMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeMappingRepository::class)]
#[ORM\Table(name: 'theme_mapping')]
class ThemeMapping implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $bggCategory = '';

    #[ORM\Column(length: 100)]
    private string $themeGroup = '';

    #[ORM\Column(length: 255)]
    private string $themeLabel = '';

    #[ORM\Column(length: 10)]
    private string $themeEmoji = '🏷️';

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'bggCategory' => $this->bggCategory,
            'themeGroup'  => $this->themeGroup,
            'themeLabel'  => $this->themeLabel,
            'themeEmoji'  => $this->themeEmoji,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getBggCategory(): string { return $this->bggCategory; }
    public function setBggCategory(string $v): static { $this->bggCategory = $v; return $this; }
    public function getThemeGroup(): string { return $this->themeGroup; }
    public function setThemeGroup(string $v): static { $this->themeGroup = $v; return $this; }
    public function getThemeLabel(): string { return $this->themeLabel; }
    public function setThemeLabel(string $v): static { $this->themeLabel = $v; return $this; }
    public function getThemeEmoji(): string { return $this->themeEmoji; }
    public function setThemeEmoji(string $v): static { $this->themeEmoji = $v; return $this; }
}
