<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mapping expert des 105 mécaniques BGG non encore classées dans les familles Engelstein.
 *
 * Logique appliquée :
 *  - ENGINE families (6) : worker_placement, deck_building, engine_building,
 *    area_control, hand_management, auction
 *  - SUPPORT families (8) : card_play, spatial_placement, movement,
 *    resource_management, resolution, uncertainty, cooperation, scoring
 */
final class Version20260606000008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map 105 unmapped BGG mechanics to Engelstein families (expert classification)';
    }

    public function up(Schema $schema): void
    {
        $mappings = [
            // ── SPATIAL_PLACEMENT ─────────────────────────────────────────────────────
            // Variantes de grilles, construction de cartes/tuiles, topologie du plateau
            ['Square Grid',              'spatial_placement'],
            ['Enclosure',                'spatial_placement'], // entourer une zone avec des pièces
            ['Network and Route Building','spatial_placement'], // construire des réseaux sur le plateau
            ['Line Drawing',             'spatial_placement'], // tracer des lignes sur le plateau
            ['Map Addition',             'spatial_placement'], // ajouter des tuiles/sections à la carte
            ['Connections',              'spatial_placement'], // relier des points sur le plateau
            ['Layering',                 'spatial_placement'], // empiler des pièces en couches
            ['Stacking and Balancing',   'spatial_placement'], // équilibre physique de pièces
            ['Crayon Rail System',       'spatial_placement'], // tracer des voies ferroviaires au crayon
            ['Map Reduction',            'spatial_placement'],
            ['Map Deformation',          'spatial_placement'], // modifier la forme du plateau
            ['Pieces as Map',            'spatial_placement'], // les pièces constituent la carte
            ['Multiple Maps',            'spatial_placement'],
            ['Physical Removal',         'spatial_placement'],
            ['Neighbor Scope',           'spatial_placement'], // effets limités aux cases adjacentes
            ['Flicking',                 'spatial_placement'], // lancer physiquement des pièces

            // ── MOVEMENT ──────────────────────────────────────────────────────────────
            // Déplacement de pièces selon des règles spécifiques
            ['Measurement Movement',     'movement'], // déplacement mesuré en cm/pouces (wargames, minis)
            ['Line of Sight',            'movement'], // visibilité pour tir/déplacement
            ['Three Dimensional Movement','movement'],
            ['Relative Movement',        'movement'],
            ['Pattern Movement',         'movement'], // déplacement selon un pattern fixe (ex : cavalier aux échecs)
            ['Movement Template',        'movement'], // gabarit de mouvement (X-Wing)
            ['Slide / Push',             'movement'], // pousser ou faire glisser des pièces
            ['Resource to Move',         'movement'], // dépenser des ressources pour se déplacer
            ['Mancala',                  'movement'], // distribution de graines le long d'un circuit
            ['Different Dice Movement',  'movement'],
            ['Moving Multiple Units',    'movement'],
            ['Impulse Movement',         'movement'], // mouvement par impulsions successives

            // ── AREA_CONTROL ──────────────────────────────────────────────────────────
            // Contrôle de zones, territorial, capture
            ['Zone of Control',          'area_control'], // unités exercent une influence sur cases adjacentes
            ['Area-Impulse',             'area_control'], // système d'activation par zone (wargames)
            ['Ownership',                'area_control'],
            ['Static Capture',           'area_control'], // capture de pièces statiques (échecs, go)
            ['King of the Hill',         'area_control'], // contrôler la zone centrale/clé
            ['Tug of War',               'area_control'], // track de poussée-traction entre camps
            ['Kill Steal',               'area_control'], // voler un objectif qu'un adversaire s'apprêtait à prendre
            ['Alliances',                'cooperation'],  // → cooperation car c'est une mécanique relationnelle

            // ── ENGINE_BUILDING ───────────────────────────────────────────────────────
            // Construction de combos, arbres technologiques, chaînes d'effets
            ['Tech Trees / Tech Tracks', 'engine_building'], // développer ses capacités via arbre de tech
            ['Chaining',                 'engine_building'], // déclencher des effets en chaîne

            // ── HAND_MANAGEMENT ───────────────────────────────────────────────────────
            // Gérer, combiner, jouer des cartes de sa main
            ['Multi-Use Cards',          'hand_management'], // chaque carte a plusieurs usages possibles
            ['Melding and Splaying',     'hand_management'], // grouper et étaler des cartes de sa main
            ['Closed Drafting',          'hand_management'], // draft secret (7 Wonders) = gestion de main
            ['Command Cards',            'hand_management'], // cartes de commandement pour activer des unités
            ['Move Through Deck',        'hand_management'], // se déplacer à travers son deck

            // ── CARD_PLAY ─────────────────────────────────────────────────────────────
            // Jouer des cartes comme mécanisme principal (sans deck building)
            ['Campaign / Battle Card Driven', 'card_play'], // cartes pilotent les batailles/campagnes
            ['Action Drafting',          'card_play'],   // sélectionner des actions comme des cartes
            ['Interrupts',               'card_play'],   // jouer des cartes en réaction
            ['Matching',                 'card_play'],   // associer des cartes/tuiles identiques
            ['Drawing',                  'card_play'],   // piocher des cartes comme action principale

            // ── AUCTION ───────────────────────────────────────────────────────────────
            ['Auction: Dutch',           'auction'],
            ['Constrained Bidding',      'auction'],
            ['Turn Order: Auction',      'auction'],   // enchères pour l'ordre du tour
            ['Auction: Multiple Lot',    'auction'],
            ['Closed Economy Auction',   'auction'],
            ['Auction: Fixed Placement', 'auction'],
            ['Selection Order Bid',      'auction'],
            ['Predictive Bid',           'auction'],
            ['Bids As Wagers',           'auction'],
            ['Auction: Turn Order Until Pass', 'auction'],
            ['Auction Compensation',     'auction'],
            ['I Cut, You Choose',        'auction'],   // division équitable d'un lot = enchère implicite

            // ── RESOURCE_MANAGEMENT ───────────────────────────────────────────────────
            ['Delayed Purchase',         'resource_management'], // achat différé, réception plus tard
            ['Automatic Resource Growth','resource_management'], // ressources croissent automatiquement
            ['Resource Queue',           'resource_management'],
            ['Victory Points as a Resource', 'resource_management'], // PV utilisés comme ressource
            ['Ownership',                'resource_management'], // doublon possible — garder area_control

            // ── RESOLUTION ────────────────────────────────────────────────────────────
            // Mécanismes de résolution d'actions, d'ordre du tour, de fin de partie
            ['Ratio / Combat Results Table', 'resolution'], // table CRT des wargames
            ['Variable Phase Order',     'resolution'],
            ['Action Queue',             'resolution'],  // file d'actions programmées
            ['Turn Order: Progressive',  'resolution'],
            ['Real-Time',                'resolution'],
            ['Lose a Turn',              'resolution'],
            ['Turn Order: Claim Action', 'resolution'],
            ['Turn Order: Stat-Based',   'resolution'],
            ['Elapsed Real Time Ending', 'resolution'],
            ['Sudden Death Ending',      'resolution'],
            ['Rondel',                   'resolution'],  // sélection d'action sur une roue
            ['Follow',                   'resolution'],  // suivre l'action choisie par un autre joueur
            ['Die Icon Resolution',      'resolution'],
            ['Action Retrieval',         'resolution'],  // récupérer ses actions dépensées (Viticulture)
            ['Critical Hits and Failures','resolution'],
            ['Turn Order: Time Track',   'resolution'],
            ['Turn Order: Pass Order',   'resolution'],
            ['Turn Order: Random',       'resolution'],
            ['Minimap Resolution',       'resolution'],
            ['Force Commitment',         'resolution'],
            ['Stat Check Resolution',    'resolution'],
            ['Passed Action Token',      'resolution'],
            ['Action Timer',             'resolution'],
            ['Finale Ending',            'resolution'],
            ['Advantage Token',          'resolution'],

            // ── UNCERTAINTY ───────────────────────────────────────────────────────────
            // Hasard, information cachée, mécanique d'incertitude
            ['Re-rolling and Locking',   'uncertainty'], // Yahtzee-style
            ['Bias',                     'uncertainty'],
            ['Bingo',                    'uncertainty'], // tirage aléatoire pur
            ['Cube Tower',               'uncertainty'], // tour qui filtre les dés
            ['Speed Matching',           'uncertainty'], // correspondance en temps réel = pression/hasard
            ['Hot Potato',               'uncertainty'], // passer un objet rapidement = pression
            ['Roles with Asymmetric Information', 'uncertainty'],
            ['Induction',                'uncertainty'], // déduction/induction logique

            // ── COOPERATION ───────────────────────────────────────────────────────────
            // Interaction sociale, communication, narration partagée
            ['Communication Limits',     'cooperation'],  // limites sur ce que les coéquipiers peuvent dire
            ['Targeted Clues',           'cooperation'],  // donner des indices ciblés aux coéquipiers
            ['Narrative Choice / Paragraph', 'cooperation'], // choix narratif, livre-jeu

            // ── SCORING ───────────────────────────────────────────────────────────────
            // Systèmes de fin de partie, comptage des points
            ['Score-and-Reset Game',     'scoring'],
            ['Highest-Lowest Scoring',   'scoring'],
            ['Single Loser Game',        'scoring'],
            ['King of the Hill',         'scoring'],  // doublon possible — garder area_control

            // ── SPATIAL_PLACEMENT (suite) ─────────────────────────────────────────────
            ['Spelling',                 'card_play'],   // épeler avec des lettres = gestion de tuiles/cartes
            ['Singing',                  'cooperation'], // chanter ensemble = interaction sociale

            // Inclassables rares — résolution par défaut
            ['Area-Impulse',             'area_control'],
        ];

        // Dédoublonnage : un bggMechanic ne doit apparaître qu'une fois
        $seen = [];
        $unique = [];
        foreach ($mappings as $m) {
            if (!isset($seen[$m[0]])) {
                $seen[$m[0]] = true;
                $unique[] = $m;
            }
        }

        foreach ($unique as [$mechanic, $family]) {
            $this->addSql(
                "INSERT INTO mechanic_mapping (bgg_mechanic, engelstein_family)
                 VALUES (?, ?)
                 ON CONFLICT (bgg_mechanic) DO NOTHING",
                [$mechanic, $family]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $mechanics = [
            'Square Grid','Enclosure','Network and Route Building','Line Drawing',
            'Campaign / Battle Card Driven','Line of Sight','Measurement Movement',
            'Ratio / Combat Results Table','Zone of Control','Spelling',
            'Variable Phase Order','Action Queue','Singing','Turn Order: Progressive',
            'Real-Time','Area-Impulse','Map Addition','Connections','Lose a Turn',
            'Multi-Use Cards','Tech Trees / Tech Tracks','Layering','Chaining',
            'Turn Order: Claim Action','Action Drafting','Delayed Purchase',
            'Communication Limits','Three Dimensional Movement','Sudden Death Ending',
            'Closed Drafting','Ownership','Rondel','Turn Order: Stat-Based','Bingo',
            'Victory Points as a Resource','Re-rolling and Locking','Elapsed Real Time Ending',
            'Stacking and Balancing','Bias','Follow','Die Icon Resolution',
            'Narrative Choice / Paragraph','Action Retrieval','Critical Hits and Failures',
            'Turn Order: Time Track','Automatic Resource Growth','Score-and-Reset Game',
            'Static Capture','Roles with Asymmetric Information','Alliances',
            'Turn Order: Pass Order','Crayon Rail System','Auction: Dutch','Mancala',
            'Constrained Bidding','Kill Steal','I Cut, You Choose','Highest-Lowest Scoring',
            'Relative Movement','Move Through Deck','Movement Template','Multiple Maps',
            'Pattern Movement','Melding and Splaying','Finale Ending','Single Loser Game',
            'Matching','Advantage Token','Tug of War','Minimap Resolution','Interrupts',
            'Slide / Push','Map Reduction','Map Deformation','Pieces as Map','Resource to Move',
            'Force Commitment','Stat Check Resolution','Neighbor Scope','King of the Hill',
            'Turn Order: Auction','Resource Queue','Auction: Multiple Lot','Speed Matching',
            'Closed Economy Auction','Physical Removal','Cube Tower','Command Cards',
            'Auction: Fixed Placement','Turn Order: Random','Selection Order Bid',
            'Targeted Clues','Drawing','Different Dice Movement','Predictive Bid',
            'Passed Action Token','Bids As Wagers','Moving Multiple Units','Hot Potato',
            'Flicking','Action Timer','Auction: Turn Order Until Pass','Auction Compensation',
            'Induction','Impulse Movement',
        ];

        $placeholders = implode(', ', array_fill(0, count($mechanics), '?'));
        $this->addSql("DELETE FROM mechanic_mapping WHERE bgg_mechanic IN ($placeholders)", $mechanics);
    }
}
