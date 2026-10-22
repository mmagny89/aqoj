/**
 * Mapping des catégories BGG → label français + emoji + groupe.
 *
 * Deux groupes :
 *  - "theme"  : univers / cadre narratif (Fantasy, Antiquité, SF…)
 *  - "type"   : nature du jeu (Jeu de cartes, Wargame, Party game…)
 */

export const CATEGORY_MAP = {
  // ── Thèmes ────────────────────────────────────────────────────────────
  'Fantasy':                  { label: 'Fantastique',          emoji: '🧙', group: 'theme' },
  'Science Fiction':          { label: 'Science-fiction',      emoji: '🚀', group: 'theme' },
  'Medieval':                 { label: 'Médiéval',             emoji: '⚔️',  group: 'theme' },
  'Ancient':                  { label: 'Antiquité',            emoji: '🏛️',  group: 'theme' },
  'World War II':             { label: 'Seconde Guerre mondiale', emoji: '🪖', group: 'theme' },
  'World War I':              { label: 'Première Guerre mondiale', emoji: '🪖', group: 'theme' },
  'Horror':                   { label: 'Horreur',              emoji: '👻', group: 'theme' },
  'Pirates':                  { label: 'Pirates',              emoji: '🏴‍☠️', group: 'theme' },
  'Mythology':                { label: 'Mythologie',           emoji: '🔱', group: 'theme' },
  'Space Exploration':        { label: 'Espace',               emoji: '🌌', group: 'theme' },
  'Adventure':                { label: 'Aventure',             emoji: '🗺️',  group: 'theme' },
  'Zombies':                  { label: 'Zombies',              emoji: '🧟', group: 'theme' },
  'Civilization':             { label: 'Civilisation',         emoji: '🏯', group: 'theme' },
  'Spies / Secret Agents':    { label: 'Espionnage',           emoji: '🕵️',  group: 'theme' },
  'Prehistoric':              { label: 'Préhistoire',          emoji: '🦕', group: 'theme' },
  'Farming':                  { label: 'Agriculture',          emoji: '🌾', group: 'theme' },
  'City Building':            { label: 'Construction de ville',emoji: '🏙️',  group: 'theme' },
  'Trains':                   { label: 'Trains',               emoji: '🚂', group: 'theme' },
  'Economic':                 { label: 'Économie',             emoji: '💰', group: 'theme' },
  'Political':                { label: 'Politique',            emoji: '🏛️',  group: 'theme' },
  'Exploration':              { label: 'Exploration',          emoji: '🧭', group: 'theme' },
  'Fighting':                 { label: 'Combat',               emoji: '🥊', group: 'theme' },
  'Animals':                  { label: 'Animaux',              emoji: '🐾', group: 'theme' },
  'Nautical':                 { label: 'Nautique',             emoji: '⚓', group: 'theme' },
  'Environmental':            { label: 'Environnement',        emoji: '🌿', group: 'theme' },
  'American West':            { label: 'Far West',             emoji: '🤠', group: 'theme' },
  'Murder / Mystery':         { label: 'Crime / Mystère',      emoji: '🔍', group: 'theme' },
  'Negotiation':              { label: 'Négociation',          emoji: '🤝', group: 'theme' },
  'Religious':                { label: 'Religion / Mythes',    emoji: '✝️',  group: 'theme' },
  'Aviation / Flight':        { label: 'Aviation',             emoji: '✈️',  group: 'theme' },
  'Racing':                   { label: 'Course',               emoji: '🏁', group: 'theme' },
  'Sports':                   { label: 'Sport',                emoji: '⚽', group: 'theme' },
  'Napoleonic':               { label: 'Napoléonien',          emoji: '🎖️',  group: 'theme' },
  'American Civil War':       { label: 'Guerre de Sécession',  emoji: '🎖️',  group: 'theme' },
  'Industry / Manufacturing': { label: 'Industrie',            emoji: '🏭', group: 'theme' },
  'Transportation':           { label: 'Transport',            emoji: '🚌', group: 'theme' },
  'Comic Book / Strip':       { label: 'Comics / BD',          emoji: '💬', group: 'theme' },
  'Video Game Theme':         { label: 'Jeu vidéo',            emoji: '🎮', group: 'theme' },
  'Music':                    { label: 'Musique',              emoji: '🎵', group: 'theme' },
  'Mafia':                    { label: 'Mafia',                emoji: '🎩', group: 'theme' },
  'Travel':                   { label: 'Voyage',               emoji: '🌍', group: 'theme' },
  'Movies / TV / Radio theme':{ label: 'Cinéma / TV',          emoji: '🎬', group: 'theme' },
  'Renaissance':              { label: 'Renaissance',          emoji: '🎨', group: 'theme' },
  'Modern Warfare':           { label: 'Guerre moderne',       emoji: '🪖', group: 'theme' },
  'Territory Building':       { label: 'Conquête de territoire', emoji: '🗺️', group: 'theme' },
  'Novel-based':              { label: 'Adapté d\'un roman',   emoji: '📚', group: 'theme' },

  // ── Types de jeu ──────────────────────────────────────────────────────
  'Card Game':                { label: 'Jeu de cartes',        emoji: '🃏', group: 'type' },
  'Abstract Strategy':        { label: 'Stratégie abstraite',  emoji: '♟️',  group: 'type' },
  'Party Game':               { label: 'Party game',           emoji: '🎉', group: 'type' },
  'Children\'s Game':         { label: 'Jeu enfants',          emoji: '🧸', group: 'type' },
  'Wargame':                  { label: 'Wargame',              emoji: '⚔️',  group: 'type' },
  'Dice':                     { label: 'Dés',                  emoji: '🎲', group: 'type' },
  'Deduction':                { label: 'Déduction',            emoji: '🧩', group: 'type' },
  'Trivia':                   { label: 'Quiz / Culture',       emoji: '❓', group: 'type' },
  'Word Game':                { label: 'Jeu de mots',          emoji: '📝', group: 'type' },
  'Puzzle':                   { label: 'Puzzle / Réflexion',   emoji: '🧠', group: 'type' },
  'Miniatures':               { label: 'Figurines',            emoji: '🗿', group: 'type' },
  'Bluffing':                 { label: 'Bluff',                emoji: '🎭', group: 'type' },
  'Memory':                   { label: 'Mémoire',              emoji: '🧠', group: 'type' },
  'Educational':              { label: 'Éducatif',             emoji: '🎓', group: 'type' },
  'Humor':                    { label: 'Humour',               emoji: '😄', group: 'type' },
  'Action / Dexterity':       { label: 'Adresse / Dextérité',  emoji: '🤸', group: 'type' },
  'Collectible Components':   { label: 'Cartes à collectionner', emoji: '✨', group: 'type' },
}

/** Retourne les infos d'une catégorie BGG, ou un fallback générique. */
export function categoryInfo(bggName) {
  return CATEGORY_MAP[bggName] ?? { label: bggName, emoji: '🏷️', group: 'theme' }
}

/** Filtre les catégories connues d'une liste BGG, regroupées par group. */
export function groupCategories(bggList = []) {
  const themes = []
  const types  = []
  const other  = []

  for (const name of bggList) {
    const info = CATEGORY_MAP[name]
    if (!info) { other.push({ name, ...{ label: name, emoji: '🏷️', group: 'other' } }); continue }
    if (info.group === 'type') types.push({ name, ...info })
    else themes.push({ name, ...info })
  }

  return { themes, types, other }
}

/**
 * Catégories populaires proposées comme filtres rapides dans la recherche
 * et les recommandations. Organisées en deux groupes affichés séparément.
 */
export const FILTER_THEMES = [
  'Fantasy', 'Science Fiction', 'Medieval', 'Ancient', 'Horror',
  'Adventure', 'Civilization', 'Pirates', 'Mythology', 'Zombies',
  'Spies / Secret Agents', 'City Building', 'Economic', 'Exploration',
  'Farming', 'Nautical', 'World War II', 'Space Exploration',
]

export const FILTER_TYPES = [
  'Card Game', 'Abstract Strategy', 'Party Game', 'Wargame',
  'Deduction', 'Bluffing', 'Miniatures', 'Word Game',
]
