// Les 6 moteurs centraux du modèle LudoExplorer
export const ENGINES = {
  worker_placement: 'Placement d\'Ouvriers',
  deck_building:    'Construction de Deck',
  engine_building:  'Construction de Moteur',
  area_control:     'Contrôle de Zone',
  hand_management:  'Gestion de Main',
  auction:          'Enchères',
}

// Familles de support (mécaniques secondaires)
export const SUPPORT_FAMILIES = {
  card_play:          'Jeu de Cartes',
  spatial_placement:  'Placement Spatial',
  movement:           'Mouvement',
  resource_management:'Gestion de Ressources',
  resolution:         'Résolution',
  uncertainty:        'Hasard & Incertitude',
  cooperation:        'Coopération',
  scoring:            'Marquage de Points',
}

// Familles extra-Engelstein : modes de jeu et caractéristiques transversales
export const EXTRA_FAMILIES = {
  solo:       'Solo / Solitaire',
  real_time:  'Temps Réel',
  dexterity:  'Adresse / Dextérité',
  legacy:     'Legacy / Campagne',
}

export const ALL_FAMILIES = { ...ENGINES, ...SUPPORT_FAMILIES, ...EXTRA_FAMILIES }

// Couleurs par moteur central
export const ENGINE_COLORS = {
  worker_placement: 'bg-amber-100 text-amber-800 border-amber-200',
  deck_building:    'bg-pink-100 text-pink-800 border-pink-200',
  engine_building:  'bg-teal-100 text-teal-800 border-teal-200',
  area_control:     'bg-red-100 text-red-800 border-red-200',
  hand_management:  'bg-violet-100 text-violet-800 border-violet-200',
  auction:          'bg-lime-100 text-lime-800 border-lime-200',
}

// Couleur neutre pour les familles de support
export const SUPPORT_COLOR = 'bg-stone-100 text-stone-600 border-stone-200'

// Couleurs pour les familles extra
export const EXTRA_COLORS = {
  solo:       'bg-sky-100 text-sky-700 border-sky-200',
  real_time:  'bg-orange-100 text-orange-700 border-orange-200',
  dexterity:  'bg-rose-100 text-rose-700 border-rose-200',
  legacy:     'bg-indigo-100 text-indigo-700 border-indigo-200',
}

export function isEngine(key) {
  return key in ENGINES
}

export function isExtra(key) {
  return key in EXTRA_FAMILIES
}

export function familyLabel(key) {
  return ALL_FAMILIES[key] ?? key
}

export function familyColor(key) {
  return ENGINE_COLORS[key] ?? EXTRA_COLORS[key] ?? SUPPORT_COLOR
}
