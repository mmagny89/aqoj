<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix BGG mechanic name mismatches, promote Set Collection & Tile Placement to central, add missing mechanics';
    }

    public function up(Schema $schema): void
    {
        // ---------------------------------------------------------------
        // 1. Corrections de noms BGG (espaces autour des "/" notamment)
        // ---------------------------------------------------------------
        $renames = [
            'Auction/Bidding'                  => 'Auction / Bidding',
            'Auction: English'                 => 'Auction: English Auction',
            'Auction: Sealed Bid'              => 'Auction: Sealed Bid',   // inchangé — vérification
            'Auction: Dutch'                   => 'Auction: Dutch Auction',
            'Auction: Once Around'             => 'Auction: Once Around',
            'Deck Construction'                => 'Deck Construction',     // inchangé
            'Area Control / Area Influence'    => 'Area Control',
            'Hexagonal Grid'                   => 'Hexagon Grid',
            'Worker Placement with Dice Workers' => 'Worker Placement with Dice Workers', // inchangé
            'Area Control / Area Influence'    => 'Area Control',
        ];

        foreach ($renames as $old => $new) {
            if ($old !== $new) {
                $this->addSql(
                    'UPDATE mechanic_mapping SET bgg_mechanic = ? WHERE bgg_mechanic = ?',
                    [$new, $old]
                );
            }
        }

        // ---------------------------------------------------------------
        // 2. Mécaniques qui PEUVENT être moteur central — passer à true
        // ---------------------------------------------------------------
        $promoteTocentral = [
            'Set Collection',  // Azul, Ticket to Ride, Wingspan (objectifs d'oiseaux)
            'Tile Placement',  // Carcassonne, Azul, Patchwork
            'Open Drafting',   // 7 Wonders, Sushi Go — le draft IS le jeu
        ];

        foreach ($promoteTocentral as $mechanic) {
            $this->addSql(
                'UPDATE mechanic_mapping SET is_central_capable = true WHERE bgg_mechanic = ?',
                [$mechanic]
            );
        }

        // ---------------------------------------------------------------
        // 3. Ajout des mécaniques manquantes fréquentes
        // ---------------------------------------------------------------
        $newMappings = [
            // Moteurs centraux manquants
            ['Auction / Bidding',           'auction',              true,  'Miser des ressources pour remporter des avantages (Modern Art, Power Grid)'],
            ['Auction: Dutch Auction',       'auction',              true,  'Enchères descendantes'],
            ['Auction: English Auction',     'auction',              true,  'Enchères montantes classiques'],
            ['Area Control',                 'area_control',         true,  'Contrôler des zones pour gagner points ou ressources'],
            ['Area Majority / Influence',    'area_control',         true,  'Avoir la plus forte présence dans des zones'],

            // Placement spatial — Tile Placement est déjà dans le mapping, ces variantes manquent
            ['Roll / Spin and Move',         'movement',             false, 'Lancer un dé pour déterminer le nombre de cases à avancer'],
            ['Movement Points',              'movement',             false, 'Budget de points de mouvement à dépenser pour déplacer des pièces'],
            ['Chit-Pull System',             'uncertainty',          false, 'Piocher des jetons au hasard pour déclencher des événements'],

            // Résolution / interaction
            ['Action / Event',               'resolution',           false, 'Jouer une carte pour son action OU son événement'],
            ['Acting',                       'resolution',           false, 'Mimer ou jouer la comédie pour faire deviner (Charades, Concept)'],
            ['Memory',                       'resolution',           false, 'Retourner et mémoriser des éléments cachés'],
            ['Pattern Recognition',          'resolution',           false, 'Identifier des formes ou séquences pour marquer des points'],
            ['Betting and Bluffing',         'uncertainty',          false, 'Parier sur une incertitude avec possibilité de bluff'],
            ['Deduction',                    'uncertainty',          false, 'Déduire des informations cachées par élimination logique (Clue, Mysterium)'],
            ['Hidden Movement',              'uncertainty',          false, 'Un joueur se déplace secrètement sur le plateau (Scotland Yard)'],

            // Jeu de cartes
            ['Drafting',                     'card_play',            false, 'Sélectionner des éléments parmi une offre commune (générique)'],
            ['Card Play Conflict Resolution','card_play',            false, 'Résolution de conflits via des cartes jouées simultanément'],
            ['Ladder Climbing',              'card_play',            true,  'Surenchérir sur la combinaison précédente (Tichu, Big Two)'],

            // Gestion de ressources
            ['Loans',                        'resource_management',  false, 'Emprunter des ressources avec intérêts à rembourser'],
            ['Commodity Speculation',        'resource_management',  false, 'Spéculer sur la valeur future des ressources'],

            // Coopération / interaction
            ['Scenario / Mission / Campaign Game', 'cooperation',    false, 'Partie(s) organisée(s) en scénarios ou campagne narrative'],
            ['Roleplaying',                  'cooperation',          false, 'Incarner un personnage avec une identité narrative'],
            ['Role Playing',                 'cooperation',          false, 'Incarner un personnage avec une identité narrative'],
            ['Storytelling',                 'cooperation',          false, 'Construire collectivement un récit à partir de contraintes'],
            ['Player Judge',                 'cooperation',          false, 'Un joueur juge les réponses des autres (Dixit, Cards Against Humanity)'],

            // Marquage de points / structure
            ['Paper-and-Pencil',             'scoring',              false, 'Le jeu utilise papier et crayon pour enregistrer les décisions'],
            ['Simulation',                   'scoring',              false, 'Modélisation d\'un système réel (guerre, économie, sport)'],
            ['Legacy Game',                  'scoring',              false, 'Les décisions d\'une partie modifient définitivement le jeu (Pandemic Legacy)'],
            ['Catch the Leader',             'scoring',              false, 'Mécanismes rééquilibrant automatiquement les écarts de score'],
        ];

        foreach ($newMappings as [$mechanic, $family, $central, $desc]) {
            // INSERT OR IGNORE — on ne touche pas aux mappings déjà présents
            $this->addSql(
                'INSERT INTO mechanic_mapping (bgg_mechanic, engelstein_family, is_central_capable, description)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (bgg_mechanic) DO NOTHING',
                [$mechanic, $family, $central ? 'true' : 'false', $desc]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Pas de rollback sur les données de seed
    }
}
