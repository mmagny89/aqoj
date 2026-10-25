<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\GameRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Catalogue exposé en lecture seule sous /api/catalogue/games (BG-TCG s'y alimente).
 * Les routes /api/games* du site sont des contrôleurs distincts et ne changent pas.
 * Seuls les champs du groupe `catalogue:read` sortent : rien de privé (possession, notes, essais BGG).
 */
#[ApiResource(
    uriTemplate: '/catalogue/games',
    operations: [
        new GetCollection(
            uriTemplate: '/catalogue/games',
            parameters: [
                'name' => new QueryParameter(filter: new PartialSearchFilter(), property: 'name'),
                'bggType' => new QueryParameter(filter: new ExactFilter(), property: 'bggType'),
                'isExpansion' => new QueryParameter(filter: new BooleanFilter(), property: 'isExpansion'),
                'yearPublished' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'yearPublished'),
                'complexity' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'complexity'),
                'playingTime' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'playingTime'),
                'minPlayers' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'minPlayers'),
                'maxPlayers' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'maxPlayers'),
                'bggRank' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'bggRank'),
                'usersRated' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'usersRated'),
                'lastSyncedAt' => new QueryParameter(filter: new ComparisonFilter(new ExactFilter()), property: 'lastSyncedAt'),
                'order[:property]' => new QueryParameter(
                    filter: new SortFilter(),
                    properties: ['bggId', 'name', 'yearPublished', 'complexity', 'bggRank', 'usersRated', 'lastSyncedAt'],
                ),
            ],
        ),
        new Get(uriTemplate: '/catalogue/games/{bggId}'),
    ],
    normalizationContext: ['groups' => ['catalogue:read'], 'skip_null_values' => false],
    order: ['bggId' => 'ASC'],
)]
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
class Game implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['catalogue:read'])]
    private string $bggId;

    #[ORM\Column(length: 255)]
    #[Groups(['catalogue:read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['catalogue:read'])]
    private int $minPlayers = 1;

    #[ORM\Column]
    #[Groups(['catalogue:read'])]
    private int $maxPlayers = 1;

    #[ORM\Column]
    #[Groups(['catalogue:read'])]
    private int $playingTime = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['catalogue:read'])]
    private float $complexity = 0.0;

    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $categories = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $mechanics = [];

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?string $imageUrl = null;

    // Édition française (BGG, `versions=1`) : propre à ce site, hors du groupe publié à BG-TCG
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $editionFrPublisher = null;

    #[ORM\Column(nullable: true)]
    private ?int $editionFrYear = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $editionFrImageUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?int $yearPublished = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?float $ratingBgg = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, options: ['default' => 'bgg'])]
    private string $source = 'bgg';

    #[ORM\Column(nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    /** Dernier appel BGG à son sujet, réussi ou non : évite de rappeler à chaque passage un jeu absent de la réponse. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastFetchAttemptAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?int $bggRank = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?int $usersRated = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isExpansion = false;

    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $mechanicFamilies = [];

    /** Moteurs centraux détectés automatiquement (sous-ensemble de mechanicFamilies, isCentralCapable=true) */
    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $detectedEngines = [];

    /** Moteur principal validé manuellement par un admin (écrase detectedEngines pour l'affichage) */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?string $primaryEngine = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?int $minAge = null;

    /**
     * Nom d'origine BGG (toujours en anglais).
     * `name` contient le nom d'affichage (FR si disponible, sinon identique à nameOriginal).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['catalogue:read'])]
    private ?string $nameOriginal = null;

    /** Type BGG brut : 'boardgame' ou 'boardgameexpansion' */
    #[ORM\Column(length: 30, options: ['default' => 'boardgame'])]
    #[Groups(['catalogue:read'])]
    private string $bggType = 'boardgame';

    /** BGG IDs (string) des extensions de ce jeu */
    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $expansionBggIds = [];

    /** BGG IDs (string) des jeux dont celui-ci est une réédition/version collector */
    #[ORM\Column(type: 'json')]
    #[Groups(['catalogue:read'])]
    private array $implementsBggIds = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'bggId' => $this->bggId,
            'name' => $this->name,
            'description' => $this->description,
            'minPlayers' => $this->minPlayers,
            'maxPlayers' => $this->maxPlayers,
            'playingTime' => $this->playingTime,
            'complexity' => $this->complexity,
            'categories' => $this->categories,
            'mechanics' => $this->mechanics,
            'imageUrl' => $this->imageUrl,
            'editionFrPublisher' => $this->editionFrPublisher,
            'editionFrYear' => $this->editionFrYear,
            'editionFrImageUrl' => $this->editionFrImageUrl,
            'yearPublished' => $this->yearPublished,
            'ratingBgg' => $this->ratingBgg,
            'source' => $this->source,
            'lastSyncedAt' => $this->lastSyncedAt?->format('c'),
            'bggRank' => $this->bggRank,
            'usersRated' => $this->usersRated,
            'isExpansion' => $this->isExpansion,
            'mechanicFamilies' => $this->mechanicFamilies,
            'detectedEngines' => $this->detectedEngines,
            'primaryEngine' => $this->primaryEngine,
            'displayEngines'    => $this->getDisplayEngines(),
            'minAge'            => $this->minAge,
            'nameOriginal'      => $this->nameOriginal,
            'bggType'           => $this->bggType,
            'expansionBggIds'   => $this->expansionBggIds,
            'implementsBggIds'  => $this->implementsBggIds,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getBggId(): string { return $this->bggId; }
    public function setBggId(string $bggId): static { $this->bggId = $bggId; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getMinPlayers(): int { return $this->minPlayers; }
    public function setMinPlayers(int $minPlayers): static { $this->minPlayers = $minPlayers; return $this; }
    public function getMaxPlayers(): int { return $this->maxPlayers; }
    public function setMaxPlayers(int $maxPlayers): static { $this->maxPlayers = $maxPlayers; return $this; }
    public function getPlayingTime(): int { return $this->playingTime; }
    public function setPlayingTime(int $playingTime): static { $this->playingTime = $playingTime; return $this; }
    public function getComplexity(): float { return $this->complexity; }
    public function setComplexity(float $complexity): static { $this->complexity = $complexity; return $this; }
    public function getCategories(): array { return $this->categories; }
    public function setCategories(array $categories): static { $this->categories = $categories; return $this; }
    public function getMechanics(): array { return $this->mechanics; }
    public function setMechanics(array $mechanics): static { $this->mechanics = $mechanics; return $this; }
    public function getEditionFrPublisher(): ?string { return $this->editionFrPublisher; }
    public function setEditionFrPublisher(?string $v): static { $this->editionFrPublisher = $v; return $this; }
    public function getEditionFrYear(): ?int { return $this->editionFrYear; }
    public function setEditionFrYear(?int $v): static { $this->editionFrYear = $v; return $this; }
    public function getEditionFrImageUrl(): ?string { return $this->editionFrImageUrl; }
    public function setEditionFrImageUrl(?string $v): static { $this->editionFrImageUrl = $v; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getYearPublished(): ?int { return $this->yearPublished; }
    public function setYearPublished(?int $yearPublished): static { $this->yearPublished = $yearPublished; return $this; }
    public function getRatingBgg(): ?float { return $this->ratingBgg; }
    public function setRatingBgg(?float $ratingBgg): static { $this->ratingBgg = $ratingBgg; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getLastSyncedAt(): ?\DateTimeImmutable { return $this->lastSyncedAt; }
    public function setLastSyncedAt(\DateTimeImmutable $dt): static { $this->lastSyncedAt = $dt; return $this; }
    public function getLastFetchAttemptAt(): ?\DateTimeImmutable { return $this->lastFetchAttemptAt; }
    public function setLastFetchAttemptAt(\DateTimeImmutable $dt): static { $this->lastFetchAttemptAt = $dt; return $this; }
    public function getBggRank(): ?int { return $this->bggRank; }
    public function setBggRank(?int $bggRank): static { $this->bggRank = $bggRank; return $this; }
    public function getUsersRated(): ?int { return $this->usersRated; }
    public function setUsersRated(?int $usersRated): static { $this->usersRated = $usersRated; return $this; }
    #[Groups(['catalogue:read'])]
    #[SerializedName('isExpansion')]
    public function isExpansion(): bool { return $this->isExpansion; }
    public function setIsExpansion(bool $isExpansion): static { $this->isExpansion = $isExpansion; return $this; }
    public function getMechanicFamilies(): array { return $this->mechanicFamilies; }
    public function setMechanicFamilies(array $families): static { $this->mechanicFamilies = $families; return $this; }
    public function getDetectedEngines(): array { return $this->detectedEngines; }
    public function setDetectedEngines(array $engines): static { $this->detectedEngines = $engines; return $this; }
    public function getPrimaryEngine(): ?string { return $this->primaryEngine; }
    public function setPrimaryEngine(?string $engine): static { $this->primaryEngine = $engine; return $this; }

    public function getExpansionBggIds(): array { return $this->expansionBggIds; }
    public function setExpansionBggIds(array $ids): static { $this->expansionBggIds = $ids; return $this; }
    public function getImplementsBggIds(): array { return $this->implementsBggIds; }
    public function setImplementsBggIds(array $ids): static { $this->implementsBggIds = $ids; return $this; }
    public function getMinAge(): ?int { return $this->minAge; }
    public function setMinAge(?int $minAge): static { $this->minAge = $minAge; return $this; }
    public function getNameOriginal(): ?string { return $this->nameOriginal; }
    public function setNameOriginal(?string $n): static { $this->nameOriginal = $n; return $this; }
    public function getBggType(): string { return $this->bggType; }
    public function setBggType(string $t): static { $this->bggType = $t; return $this; }

    /** Retourne le(s) moteur(s) à afficher : primaryEngine s'il est défini, sinon detectedEngines */
    public function getDisplayEngines(): array
    {
        if ($this->primaryEngine !== null) {
            return [$this->primaryEngine];
        }
        return $this->detectedEngines;
    }
}
