<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add detected_engines and primary_engine to game; reseed mechanic_mapping with corrected 6-engine taxonomy';
    }

    public function up(Schema $schema): void
    {
        // Nouveaux champs sur game
        $this->addSql("ALTER TABLE game ADD COLUMN detected_engines JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE game ADD COLUMN primary_engine VARCHAR(50) DEFAULT NULL');

        // Reseed mechanic_mapping : on repart d'une base propre
        $this->addSql('TRUNCATE TABLE mechanic_mapping RESTART IDENTITY');

        foreach ($this->getMappings() as [$mechanic, $family, $central, $desc]) {
            $this->addSql(
                "INSERT INTO mechanic_mapping (bgg_mechanic, engelstein_family, is_central_capable, description) VALUES (?, ?, ?, ?)",
                [$mechanic, $family, $central ? 'true' : 'false', $desc]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN detected_engines');
        $this->addSql('ALTER TABLE game DROP COLUMN primary_engine');
    }

    /** @return array<array{string, string, bool, string}> */
    private function getMappings(): array
    {
        return [
            // =====================================================================
            // LES 6 MOTEURS CENTRAUX (isCentralCapable = true)
            // =====================================================================

            // --- worker_placement ---
            ['Worker Placement', 'worker_placement', true, 'Placer des ouvriers sur des cases d\'action limitées pour obtenir ressources ou actions'],
            ['Worker Placement with Dice Workers', 'worker_placement', true, 'Worker placement dont la force des ouvriers dépend de dés'],
            ['Worker Placement, Different Worker Types', 'worker_placement', true, 'Worker placement avec ouvriers de types et pouvoirs différents'],

            // --- deck_building ---
            ['Deck, Bag, and Pool Building', 'deck_building', true, 'Affiner progressivement son deck, sac ou réservoir en cours de partie (Dominion, Clank!)'],
            ['Bag Building', 'deck_building', true, 'Variante du deckbuilding où on pioche des jetons dans un sac (Quacks of Quedlinburg)'],
            ['Deck Construction', 'deck_building', true, 'Construction du deck avant la partie (Magic: The Gathering)'],

            // --- engine_building ---
            ['Engine Building', 'engine_building', true, 'Construire un système produisant ressources/points avec efficacité croissante (Terraforming Mars, Wingspan)'],
            ['Tableau Building', 'engine_building', true, 'Construire un tableau de cartes/tuiles qui s\'amplifient mutuellement (Race for the Galaxy)'],
            ['Tech Trees / Researching', 'engine_building', false, 'Débloquer des améliorations via une arborescence technologique (support d\'engine building)'],
            ['Combo', 'engine_building', false, 'Enchaîner des effets pour des conversions amplifiées'],

            // --- area_control ---
            ['Area Control / Area Influence', 'area_control', true, 'Contrôler des zones du plateau pour gagner points ou ressources (Blood Rage, El Grande)'],
            ['Area Majority / Influence', 'area_control', true, 'Avoir la plus forte présence dans des zones pour en bénéficier'],
            ['Route/Network Building', 'area_control', false, 'Construire un réseau de routes ou connexions (Ticket to Ride — support spatial)'],

            // --- hand_management ---
            ['Hand Management', 'hand_management', true, 'Jouer ou conserver stratégiquement ses cartes selon le timing (Ark Nova, 7 Wonders Duel)'],
            ['Trick-taking', 'hand_management', true, 'Remporter des plis en jouant la carte la plus forte (Bridge, Tichu)'],

            // --- auction ---
            ['Auction/Bidding', 'auction', true, 'Miser des ressources pour remporter cartes, actions ou avantages (Modern Art, Power Grid)'],
            ['Auction: English', 'auction', true, 'Enchères montantes classiques, remportées par le plus offrant'],
            ['Auction: Sealed Bid', 'auction', true, 'Enchères secrètes révélées simultanément'],
            ['Auction: Dutch', 'auction', true, 'Enchères descendantes, s\'arrêtant quand un joueur accepte le prix'],
            ['Auction: Once Around', 'auction', true, 'Chaque joueur mise une seule fois, dans l\'ordre du tour'],
            ['Auction: Dexterity', 'auction', false, 'Enchères combinées à un défi d\'adresse'],

            // =====================================================================
            // FAMILLES DE SUPPORT
            // =====================================================================

            // --- card_play (Jeu de Cartes) ---
            ['Card Drafting', 'card_play', false, 'Sélectionner des cartes parmi une offre commune ou un passage entre joueurs (7 Wonders)'],
            ['Open Drafting', 'card_play', false, 'Draft où les options disponibles sont visibles de tous'],
            ['Set Collection', 'card_play', false, 'Collecter des ensembles de cartes/tuiles pour marquer des points'],
            ['Push Your Luck', 'card_play', false, 'Risquer de perdre ses gains en continuant à jouer (Quacks, Can\'t Stop)'],
            ['Take That', 'card_play', false, 'Cartes à effet offensif direct contre les autres joueurs'],
            ['Once-Per-Game Abilities', 'card_play', false, 'Capacités spéciales utilisables une seule fois par partie'],

            // --- spatial_placement (Placement Spatial) ---
            ['Tile Placement', 'spatial_placement', false, 'Placer des tuiles pour créer un plateau en expansion (Carcassonne, Azul)'],
            ['Pattern Building', 'spatial_placement', false, 'Créer des formes ou séquences spatiales pour marquer des points'],
            ['Grid Coverage', 'spatial_placement', false, 'Remplir une grille de façon optimale (puzzle spatial)'],
            ['Modular Board', 'spatial_placement', false, 'Plateau composé de tuiles aléatoires reconfigurant chaque partie'],
            ['Hexagonal Grid', 'spatial_placement', false, 'Plateau en hexagones offrant 6 directions de déplacement/placement'],

            // --- movement (Mouvement) ---
            ['Area Movement', 'movement', false, 'Se déplacer de zone en zone selon des connexions'],
            ['Grid Movement', 'movement', false, 'Se déplacer case par case sur une grille'],
            ['Pick-up and Deliver', 'movement', false, 'Ramasser des ressources et les livrer à des destinations (Brass)'],
            ['Track Movement', 'movement', false, 'Avancer sur une piste linéaire ou circulaire'],
            ['Programmed Movement', 'movement', false, 'Planifier ses déplacements à l\'avance avant révélation simultanée'],
            ['Point to Point Movement', 'movement', false, 'Se déplacer entre nœuds reliés sur une carte'],

            // --- resource_management (Gestion de Ressources) ---
            ['Resource Management', 'resource_management', false, 'Collecter, stocker et dépenser des ressources variées'],
            ['Income', 'resource_management', false, 'Revenus périodiques de ressources ou de points (Terraforming Mars, Ark Nova)'],
            ['Trading', 'resource_management', false, 'Échange direct de ressources entre joueurs'],
            ['Negotiation', 'resource_management', false, 'Négociation libre entre joueurs pour obtenir des avantages (Catan)'],
            ['Market', 'resource_management', false, 'Achat/vente de ressources sur un marché commun fluctuant'],
            ['Bribery', 'resource_management', false, 'Offrir des ressources pour influencer les décisions d\'autres joueurs'],
            ['Contracts', 'resource_management', false, 'Remplir des objectifs contre récompenses (Ark Nova)'],
            ['Increase Value of Unchosen Resources', 'resource_management', false, 'Les ressources non choisies gagnent de la valeur (Isle of Skye)'],

            // --- resolution (Résolution) ---
            ['Dice Rolling', 'resolution', false, 'Lancer des dés pour déterminer actions ou résultats'],
            ['Combat', 'resolution', false, 'Résolution de conflits armés entre unités'],
            ['Voting', 'resolution', false, 'Les joueurs votent pour déterminer un résultat collectif'],
            ['Rock-Paper-Scissors', 'resolution', false, 'Résolution par choix simultanés mutuellement antagonistes'],
            ['Action Points', 'resolution', false, 'Budget de points d\'action à dépenser librement chaque tour'],
            ['Simultaneous Action Selection', 'resolution', false, 'Tous les joueurs choisissent leurs actions en même temps'],
            ['Variable Player Powers', 'resolution', false, 'Chaque joueur possède des capacités asymétriques'],
            ['Events', 'resolution', false, 'Événements déclenchés par des cartes ou des dés affectant la partie'],

            // --- uncertainty (Hasard & Incertitude) ---
            ['Random Production', 'uncertainty', false, 'Production de ressources déterminée aléatoirement (Catan)'],
            ['Hidden Roles', 'uncertainty', false, 'Certains joueurs ont des rôles secrets (Werewolf, Secret Hitler)'],
            ['Secret Unit Deployment', 'uncertainty', false, 'Déployer des unités cachées révélées progressivement'],
            ['Hidden Victory Points', 'uncertainty', false, 'Les scores restent secrets jusqu\'à la fin de partie'],
            ['Variable Set-up', 'uncertainty', false, 'Mise en place aléatoire ou modulaire qui change chaque partie'],

            // --- cooperation (Coopération & Interaction) ---
            ['Cooperative Game', 'cooperation', false, 'Tous les joueurs gagnent ou perdent ensemble (Pandemic)'],
            ['Semi-Cooperative Game', 'cooperation', false, 'Coopération partielle avec possible gagnant unique'],
            ['Traitor Game', 'cooperation', false, 'Un ou plusieurs joueurs travaillent secrètement contre le groupe'],
            ['Team-Based Game', 'cooperation', false, 'Joueurs divisés en équipes avec objectif commun'],
            ['Solo / Solitaire Game', 'cooperation', false, 'Mode solo contre le jeu'],
            ['Player Elimination', 'cooperation', false, 'Les joueurs peuvent être éliminés en cours de partie'],

            // --- scoring (Marquage de Points) ---
            ['End Game Bonuses', 'scoring', false, 'Points bonus calculés en fin de partie selon objectifs atteints'],
            ['Race', 'scoring', false, 'Atteindre un objectif avant les autres (course)'],
            ['Tags', 'scoring', false, 'Symboles sur les cartes déclenchant des synergies ou comptant pour les objectifs (Ark Nova)'],
            ['Stock Holding', 'scoring', false, 'Acquérir des parts dans des entreprises pour des gains futurs'],
            ['Investment', 'scoring', false, 'Placer des ressources pour un retour sur investissement'],
        ];
    }
}
