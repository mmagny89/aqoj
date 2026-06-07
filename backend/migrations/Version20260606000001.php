<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mechanic_mapping table and seed Engelstein family mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mechanic_mapping (
            id SERIAL NOT NULL,
            bgg_mechanic VARCHAR(255) NOT NULL,
            engelstein_family VARCHAR(100) NOT NULL,
            is_central_capable BOOLEAN NOT NULL DEFAULT FALSE,
            description VARCHAR(500) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_mechanic_mapping_bgg ON mechanic_mapping (bgg_mechanic)');

        $mappings = $this->getSeedMappings();
        foreach ($mappings as [$mechanic, $family, $central, $desc]) {
            $this->addSql(
                "INSERT INTO mechanic_mapping (bgg_mechanic, engelstein_family, is_central_capable, description) VALUES (?, ?, ?, ?)",
                [$mechanic, $family, $central ? 'true' : 'false', $desc]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mechanic_mapping');
    }

    /** @return array<array{string, string, bool, string|null}> */
    private function getSeedMappings(): array
    {
        return [
            // [bgg_mechanic, engelstein_family, is_central_capable, description]

            // --- Action Centrale ---
            ['Dice Rolling', 'action_centrale', false, 'Les dés déterminent les actions ou résultats disponibles'],
            ['Card Drawing', 'action_centrale', false, 'Piocher des cartes est l\'action principale du tour'],
            ['Hand Management', 'jeu_cartes', true, 'Jouer ou conserver stratégiquement ses cartes selon le timing'],
            ['Action Points', 'selection_actions', true, 'Budget de points à dépenser librement chaque tour'],
            ['Variable Player Powers', 'selection_actions', false, 'Chaque joueur dispose d\'actions asymétriques'],

            // --- Sélection d\'Actions ---
            ['Worker Placement', 'selection_actions', true, 'Placer des ouvriers sur des cases limitées pour obtenir des actions'],
            ['Worker Placement with Dice Workers', 'selection_actions', true, 'Worker placement dont la valeur dépend de dés'],
            ['Action Queue', 'selection_actions', false, 'Les actions sont planifiées à l\'avance dans une file'],
            ['Role Playing', 'selection_actions', false, 'Choisir un rôle qui détermine les actions disponibles'],
            ['Simultaneous Action Selection', 'selection_actions', true, 'Tous les joueurs choisissent leurs actions en même temps'],
            ['Once-Per-Game Abilities', 'selection_actions', false, 'Capacités spéciales utilisables une seule fois par partie'],

            // --- Gestion de Ressources ---
            ['Resource Management', 'gestion_ressources', false, 'Collecter, stocker et gérer des ressources variées'],
            ['Income', 'gestion_ressources', false, 'Revenus périodiques de ressources ou de points'],
            ['Market', 'commerce', true, 'Achat et vente de ressources ou de cartes sur un marché commun'],
            ['Trading', 'commerce', true, 'Échange direct de ressources entre joueurs'],
            ['Negotiation', 'commerce', true, 'Négociation libre entre joueurs pour obtenir des avantages'],
            ['Bribery', 'commerce', false, 'Offrir des ressources pour influencer les décisions d\'autres joueurs'],

            // --- Conversion de Ressources ---
            ['Engine Building', 'conversion_ressources', true, 'Construire un moteur qui produit des ressources ou points de façon croissante'],
            ['Tech Trees / Researching', 'conversion_ressources', false, 'Débloquer des améliorations via une arborescence technologique'],
            ['Tableau Building', 'conversion_ressources', true, 'Construire un tableau de cartes/tuiles qui interagissent'],
            ['Combo', 'conversion_ressources', false, 'Enchaîner des effets pour des conversions amplifiées'],

            // --- Jeu de Cartes ---
            ['Deck, Bag, and Pool Building', 'jeu_cartes', true, 'Affiner progressivement son deck, sac ou réservoir de ressources'],
            ['Deck Construction', 'jeu_cartes', true, 'Construire son deck avant la partie (type Magic)'],
            ['Card Drafting', 'jeu_cartes', true, 'Sélectionner des cartes parmi une offre commune ou circulante'],
            ['Trick-taking', 'jeu_cartes', true, 'Remporter des plis en jouant la carte la plus forte'],
            ['Set Collection', 'jeu_cartes', true, 'Collecter des ensembles de cartes ou tuiles pour marquer des points'],
            ['Push Your Luck', 'hasard', true, 'Risquer de perdre ses gains en continuant à jouer'],

            // --- Placement ---
            ['Tile Placement', 'placement', true, 'Placer des tuiles pour créer un plateau en expansion'],
            ['Area Control / Area Influence', 'placement', true, 'Contrôler des zones du plateau pour gagner des points ou ressources'],
            ['Area Movement', 'placement', false, 'Se déplacer de zone en zone selon des règles de connexion'],
            ['Grid Movement', 'placement', false, 'Se déplacer case par case sur une grille'],
            ['Pick-up and Deliver', 'placement', true, 'Ramasser des ressources et les livrer à des destinations'],
            ['Route/Network Building', 'placement', true, 'Construire un réseau de routes ou connexions sur le plateau'],
            ['Pattern Building', 'placement', true, 'Créer des formes ou séquences spatiales pour marquer des points'],
            ['Modular Board', 'placement', false, 'Plateau composé de tuiles aléatoires à chaque partie'],

            // --- Résolution ---
            ['Combat', 'resolution', false, 'Résolution de conflits armés entre joueurs'],
            ['Voting', 'resolution', false, 'Les joueurs votent pour déterminer un résultat collectif'],
            ['Auction/Bidding', 'resolution', true, 'Miser des ressources pour remporter des avantages ou des objets'],
            ['Auction: English', 'resolution', true, 'Enchères montantes classiques'],
            ['Auction: Sealed Bid', 'resolution', true, 'Enchères secrètes révélées simultanément'],
            ['Rock-Paper-Scissors', 'resolution', false, 'Résolution par choix simultanés mutuellement antagonistes'],
            ['Programmed Movement', 'resolution', false, 'Planifier ses déplacements à l\'avance avant révélation'],

            // --- Hasard ---
            ['Random Production', 'hasard', false, 'Production de ressources déterminée aléatoirement (ex: dés de Catane)'],
            ['Hidden Roles', 'hasard', true, 'Certains joueurs ont des rôles secrets (traître, loup-garou)'],
            ['Secret Unit Deployment', 'hasard', false, 'Déployer des unités cachées révélées progressivement'],
            ['Probability Management', 'hasard', false, 'Manipuler les probabilités pour optimiser ses chances'],
            ['Bag Building', 'hasard', true, 'Piocher des jetons d\'un sac dont on améliore le contenu'],

            // --- Mode d\'interaction ---
            ['Cooperative Game', 'mode', true, 'Tous les joueurs gagnent ou perdent ensemble'],
            ['Semi-Cooperative Game', 'mode', true, 'Coopération partielle avec possibilité d\'un gagnant unique'],
            ['Team-Based Game', 'mode', true, 'Joueurs divisés en équipes avec un objectif commun'],
            ['Traitor Game', 'mode', true, 'Un ou plusieurs joueurs travaillent secrètement contre le groupe'],
            ['Player Elimination', 'mode', false, 'Les joueurs peuvent être éliminés en cours de partie'],
            ['Solo / Solitaire Game', 'mode', true, 'Conçu pour être joué seul contre le jeu'],

            // --- Investissement ---
            ['Stock Holding', 'investissement', true, 'Acquérir des parts dans des entreprises pour des gains futurs'],
            ['Loans', 'investissement', false, 'Emprunter des ressources avec intérêts à rembourser'],
            ['Investment', 'investissement', true, 'Placer des ressources pour obtenir un retour sur investissement'],
        ];
    }
}
