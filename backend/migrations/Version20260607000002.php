<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taxonomie thématique inspirée de LudoExplorer.
 *
 * Mappe les catégories BGG vers 5 groupes thématiques :
 *   universe   — Univers & fiction (Fantasy, SF, Horreur…)
 *   historical — Histoire (Antiquité, Médiéval, WWI, WWII…)
 *   game_type  — Type de jeu (Jeu de cartes, Wargame, Déduction…)
 *   society    — Société & monde réel (Économie, Politique, Transport…)
 *   culture    — Culture & divertissement (Cinéma, BD, Animaux…)
 */
final class Version20260607000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table theme_mapping et la pré-remplit avec les catégories BGG connues';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE theme_mapping (
                id           SERIAL PRIMARY KEY,
                bgg_category VARCHAR(255) NOT NULL UNIQUE,
                theme_group  VARCHAR(100) NOT NULL,
                theme_label  VARCHAR(255) NOT NULL,
                theme_emoji  VARCHAR(10)  NOT NULL DEFAULT '🏷️',
                created_at   TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
            )
        ");

        // ── Univers & fiction ───────────────────────────────────────────────
        $universe = [
            ['Fantasy',              'universe', 'Fantastique',           '🧙'],
            ['Science Fiction',      'universe', 'Science-fiction',       '🚀'],
            ['Horror',               'universe', 'Horreur',               '👻'],
            ['Mythology',            'universe', 'Mythologie',            '🔱'],
            ['Pirates',              'universe', 'Pirates',               '🏴‍☠️'],
            ['Zombies',              'universe', 'Zombies',               '🧟'],
            ['Adventure',            'universe', 'Aventure',              '🗺️'],
            ['Space Exploration',    'universe', 'Exploration spatiale',  '🌌'],
            ['Arabian',              'universe', 'Orient / Mille et une nuits', '🌙'],
            ['Spies / Secret Agents','universe', 'Espionnage',            '🕵️'],
            ['Mafia',                'universe', 'Crime organisé',        '🎩'],
            ['Murder / Mystery',     'universe', 'Crime & mystère',       '🔍'],
            ['Nautical',             'universe', 'Nautique / Mer',        '⚓'],
            ['Exploration',          'universe', 'Exploration',           '🧭'],
        ];

        // ── Histoire ────────────────────────────────────────────────────────
        $historical = [
            ['Ancient',                  'historical', 'Antiquité',                  '🏛️'],
            ['Prehistoric',              'historical', 'Préhistoire',                '🦕'],
            ['Medieval',                 'historical', 'Médiéval',                   '⚔️'],
            ['Renaissance',              'historical', 'Renaissance',                '🎨'],
            ['Age of Reason',            'historical', 'Siècle des Lumières',        '🕯️'],
            ['Napoleonic',               'historical', 'Guerres napoléoniennes',     '🎖️'],
            ['Post-Napoleonic',          'historical', 'XIXe siècle',               '🎖️'],
            ['American West',            'historical', 'Far West',                   '🤠'],
            ['American Civil War',       'historical', 'Guerre de Sécession',        '🎖️'],
            ['American Revolutionary War','historical','Révolution américaine',      '🎖️'],
            ['American Indian Wars',     'historical', 'Guerres indiennes',          '🏹'],
            ['Pike and Shot',            'historical', 'XVIe siècle',               '⚔️'],
            ['Civil War',                'historical', 'Guerre civile',              '🎖️'],
            ['World War I',              'historical', '1ère Guerre mondiale',       '🪖'],
            ['World War II',             'historical', '2ème Guerre mondiale',       '🪖'],
            ['Korean War',               'historical', 'Guerre de Corée',            '🪖'],
            ['Vietnam War',              'historical', 'Guerre du Vietnam',           '🪖'],
            ['Modern Warfare',           'historical', 'Guerre moderne',             '🪖'],
            ['Civilization',             'historical', 'Civilisations',              '🏯'],
        ];

        // ── Type de jeu ─────────────────────────────────────────────────────
        $gameType = [
            ['Card Game',             'game_type', 'Jeu de cartes',          '🃏'],
            ['Abstract Strategy',     'game_type', 'Stratégie abstraite',    '♟️'],
            ['Party Game',            'game_type', 'Party game',             '🎉'],
            ['Children\'s Game',      'game_type', 'Jeu enfants',            '🧸'],
            ['Wargame',               'game_type', 'Wargame',                '⚔️'],
            ['Dice',                  'game_type', 'Dés',                    '🎲'],
            ['Deduction',             'game_type', 'Déduction',              '🔎'],
            ['Trivia',                'game_type', 'Quiz / Culture générale', '❓'],
            ['Word Game',             'game_type', 'Jeu de mots',            '📝'],
            ['Memory',                'game_type', 'Mémoire',                '🧠'],
            ['Puzzle',                'game_type', 'Puzzle / Réflexion',     '🧩'],
            ['Bluffing',              'game_type', 'Bluff',                  '🎭'],
            ['Action / Dexterity',    'game_type', 'Adresse / Dextérité',    '🤸'],
            ['Math',                  'game_type', 'Mathématiques',          '➕'],
            ['Number',                'game_type', 'Chiffres',               '🔢'],
            ['Racing',                'game_type', 'Course',                 '🏁'],
            ['Fighting',              'game_type', 'Combat',                 '🥊'],
            ['Negotiation',           'game_type', 'Négociation',            '🤝'],
            ['Collectible Components','game_type', 'Cartes à collectionner', '✨'],
            ['Territory Building',    'game_type', 'Contrôle de territoire', '🗺️'],
            ['Miniatures',            'game_type', 'Figurines',              '🗿'],
            ['Electronic',            'game_type', 'Électronique / App',     '📱'],
            ['Maze',                  'game_type', 'Labyrinthe',             '🌀'],
            ['Real-time',             'game_type', 'Temps réel',             '⏱️'],
            ['Print & Play',          'game_type', 'Print & Play',           '🖨️'],
            ['Game System',           'game_type', 'Système de jeu',         '🎲'],
        ];

        // ── Société & monde réel ────────────────────────────────────────────
        $society = [
            ['Economic',              'society', 'Économie',               '💰'],
            ['Political',             'society', 'Politique',              '🏛️'],
            ['Religious',             'society', 'Religion',               '✝️'],
            ['Environmental',         'society', 'Environnement',          '🌿'],
            ['Medical',               'society', 'Médecine / Santé',       '🏥'],
            ['Industry / Manufacturing','society','Industrie',             '🏭'],
            ['Farming',               'society', 'Agriculture',            '🌾'],
            ['City Building',         'society', 'Construction urbaine',   '🏙️'],
            ['Transportation',        'society', 'Transport',              '🚌'],
            ['Trains',                'society', 'Trains',                 '🚂'],
            ['Aviation / Flight',     'society', 'Aviation',               '✈️'],
            ['Sports',                'society', 'Sports',                 '⚽'],
            ['Travel',                'society', 'Voyage',                 '🌍'],
            ['Educational',           'society', 'Éducatif',               '🎓'],
            ['Book',                  'society', 'Adapté d\'un livre',     '📖'],
            ['Novel-based',           'society', 'Adapté d\'un roman',     '📚'],
            ['Mature / Adult',        'society', 'Adultes',                '🔞'],
        ];

        // ── Culture & divertissement ────────────────────────────────────────
        $culture = [
            ['Movies / TV / Radio theme','culture','Cinéma / TV',          '🎬'],
            ['Comic Book / Strip',    'culture', 'Comics / BD',            '💬'],
            ['Video Game Theme',      'culture', 'Jeu vidéo',              '🎮'],
            ['Music',                 'culture', 'Musique',                '🎵'],
            ['Humor',                 'culture', 'Humour',                 '😄'],
            ['Animals',               'culture', 'Animaux',                '🐾'],
        ];

        $all = array_merge($universe, $historical, $gameType, $society, $culture);

        foreach ($all as [$bgg, $group, $label, $emoji]) {
            $this->addSql(
                "INSERT INTO theme_mapping (bgg_category, theme_group, theme_label, theme_emoji) VALUES (?, ?, ?, ?)",
                [$bgg, $group, $label, $emoji]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS theme_mapping');
    }
}
