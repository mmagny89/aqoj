<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * - Ajout colonnes expansion_bgg_ids et implements_bgg_ids sur game
 * - Nouvelles familles hors Engelstein : solo, real_time, dexterity, legacy
 * - Correction mapping : Solo / Solitaire Game → solo (était cooperation)
 * - Real-Time → real_time (était resolution)
 * - Nouveaux mappings pour ces familles
 */
final class Version20260607000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expansion/implementation links on game; add extra families (solo, real_time, dexterity, legacy)';
    }

    public function up(Schema $schema): void
    {
        // ── Nouvelles colonnes sur game ───────────────────────────────────────
        $this->addSql("ALTER TABLE game ADD COLUMN IF NOT EXISTS expansion_bgg_ids jsonb NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE game ADD COLUMN IF NOT EXISTS implements_bgg_ids jsonb NOT NULL DEFAULT '[]'");

        // ── Correction mappings existants ─────────────────────────────────────
        // Solo / Solitaire Game était faussement mappé en cooperation
        $this->addSql(
            "UPDATE mechanic_mapping SET engelstein_family = 'solo' WHERE bgg_mechanic = 'Solo / Solitaire Game'"
        );
        // Real-Time → real_time (c'est une contrainte de rythme, pas un système de résolution)
        $this->addSql(
            "UPDATE mechanic_mapping SET engelstein_family = 'real_time' WHERE bgg_mechanic = 'Real-Time'"
        );

        // ── Nouveaux mappings familles extra ──────────────────────────────────
        $mappings = [
            // SOLO
            ['Automa',                          'solo'],    // adversaire simulé pour mode solo
            ['Solitaire Game',                  'solo'],    // variante solitaire d'un jeu multijoueur

            // REAL_TIME
            ['Speed Matching',                  'real_time'],  // était uncertainty, mais c'est du temps réel
            ['Action Timer',                    'real_time'],  // était resolution, mais c'est du temps réel
            ['Elapsed Real Time Ending',        'real_time'],  // fin à l'expiration d'un timer
            ['Simultaneous Action Selection',   'real_time'],  // actions simultanées = pression temps

            // DEXTERITY
            ['Action / Dexterity',              'dexterity'],
            ['Flicking',                        'dexterity'],  // était spatial_placement
            ['Stacking and Balancing',          'dexterity'],  // était spatial_placement
            ['Physical Removal',                'dexterity'],  // était spatial_placement
            ['Singing',                         'dexterity'],  // performance physique/vocale
            ['Roleplaying',                     'dexterity'],  // performance d'acteur
            ['Mime',                            'dexterity'],  // jeu de mime = adresse expressive
            ['Paper-and-Pencil',                'dexterity'],  // dessin, traçage physique

            // LEGACY
            ['Legacy Game',                     'legacy'],
            ['Campaign / Battle Card Driven',   'legacy'],     // était card_play — structure de campagne
            ['Scenario / Mission / Campaign Game', 'legacy'],
            ['Narrative Choice / Paragraph',    'legacy'],     // était cooperation — livre-jeu/campagne
            ['Choose a Side',                   'legacy'],     // choix de camp dans une campagne asymétrique
            ['Hidden Roles',                    'uncertainty'],// rôles cachés → incertitude (déjà géré ?)
        ];

        $seen = [];
        foreach ($mappings as [$mechanic, $family]) {
            if (isset($seen[$mechanic])) {
                continue;
            }
            $seen[$mechanic] = true;
            $this->addSql(
                "INSERT INTO mechanic_mapping (bgg_mechanic, engelstein_family)
                 VALUES (?, ?)
                 ON CONFLICT (bgg_mechanic) DO UPDATE SET engelstein_family = EXCLUDED.engelstein_family",
                [$mechanic, $family]
            );
        }

        // Correction : Speed Matching et Action Timer / Elapsed Real Time Ending déjà mappés → réassigner
        $overrides = [
            ['Speed Matching',           'real_time'],
            ['Action Timer',             'real_time'],
            ['Elapsed Real Time Ending', 'real_time'],
            ['Flicking',                 'dexterity'],
            ['Stacking and Balancing',   'dexterity'],
            ['Physical Removal',         'dexterity'],
            ['Singing',                  'dexterity'],
            ['Narrative Choice / Paragraph', 'legacy'],
            ['Campaign / Battle Card Driven', 'legacy'],
        ];

        foreach ($overrides as [$mechanic, $family]) {
            $this->addSql(
                "UPDATE mechanic_mapping SET engelstein_family = ? WHERE bgg_mechanic = ?",
                [$family, $mechanic]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS expansion_bgg_ids');
        $this->addSql('ALTER TABLE game DROP COLUMN IF EXISTS implements_bgg_ids');

        // Revert les corrections de mappings
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'cooperation' WHERE bgg_mechanic = 'Solo / Solitaire Game'");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'resolution' WHERE bgg_mechanic = 'Real-Time'");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'uncertainty' WHERE bgg_mechanic = 'Speed Matching'");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'resolution' WHERE bgg_mechanic IN ('Action Timer', 'Elapsed Real Time Ending')");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'spatial_placement' WHERE bgg_mechanic IN ('Flicking', 'Stacking and Balancing', 'Physical Removal')");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'cooperation' WHERE bgg_mechanic IN ('Singing', 'Narrative Choice / Paragraph')");
        $this->addSql("UPDATE mechanic_mapping SET engelstein_family = 'card_play' WHERE bgg_mechanic = 'Campaign / Battle Card Driven'");

        $this->addSql("DELETE FROM mechanic_mapping WHERE bgg_mechanic IN (
            'Automa', 'Solitaire Game', 'Action / Dexterity', 'Legacy Game',
            'Scenario / Mission / Campaign Game', 'Simultaneous Action Selection',
            'Choose a Side', 'Hidden Roles', 'Roleplaying', 'Mime', 'Paper-and-Pencil'
        )");
    }
}
