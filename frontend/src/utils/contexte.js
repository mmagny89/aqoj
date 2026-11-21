// Filtres de contexte (« qu'est-ce qu'on joue maintenant, avec ces gens et ce temps ? ») partagés par la ludothèque,
// « À quoi on joue » et Explorer. L'état vit dans l'adresse (partageable) ; l'API reçoit les mêmes critères en anglais.

export const WEIGHTS = [
  { value: 'light', url: 'leger', label: 'Léger', hint: 'poids BGG de 2 au plus' },
  { value: 'medium', url: 'moyen', label: 'Moyen', hint: 'poids BGG de 2 à 3' },
  { value: 'heavy', url: 'costaud', label: 'Costaud', hint: 'poids BGG au-delà de 3' },
]
export const FITS = [
  { value: null, url: null, label: 'Jouable' },
  { value: 'recommended', url: 'conseille', label: 'Conseillé' },
  { value: 'best', url: 'ideal', label: 'Idéal' },
]
export const PLAYERS = [1, 2, 3, 4, 5, 6, 7, 8]
export const DURATIONS = [15, 30, 45, 60, 90, 120, 180]
export const AGES = [6, 8, 10, 12, 14]

export const NO_FILTERS = { players: null, fit: null, maxTime: null, weight: null, coop: null, age: null, played: null }

// Crans des jauges : le premier (null) est « pas de filtre ».
export const PLAYER_STEPS = [null, ...PLAYERS]
export const DURATION_STEPS = [null, ...DURATIONS]

// Un scénario remplace tous les filtres de contexte, sauf « joué ».
export const SCENARIOS = [
  { label: '4 joueurs · 1 h · léger', filters: { players: 4, maxTime: 60, weight: 'light' } },
  { label: 'À deux · 30 min', filters: { players: 2, maxTime: 30 } },
  { label: 'En famille · dès 8 ans', filters: { players: 4, age: 8, weight: 'light' } },
  { label: 'Coopératif à 4', filters: { players: 4, coop: true } },
  { label: 'Grande tablée · conseillé à 6', filters: { players: 6, fit: 'recommended' } },
]

export function durationLabel(minutes) {
  if (minutes < 60) return `${minutes} min`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m ? `${h} h ${m}` : `${h} h`
}

const positiveInt = (value) => {
  const n = Number(value)
  return Number.isInteger(n) && n > 0 ? n : null
}
const pick = (list, key, value) => list.find(item => item[key] === value && item.value !== null)?.value ?? null

export function filtersFromParams(params) {
  const mode = params.get('mode')
  const played = params.get('joue')
  return {
    players: positiveInt(params.get('joueurs')),
    fit: pick(FITS, 'url', params.get('nombre')),
    maxTime: positiveInt(params.get('duree')),
    weight: pick(WEIGHTS, 'url', params.get('poids')),
    coop: mode === 'coop' ? true : mode === 'competitif' ? false : null,
    age: positiveInt(params.get('age')),
    played: played === 'oui' ? true : played === 'non' ? false : null,
  }
}

/** Écrit les filtres dans une copie de `params` (les autres paramètres, comme `q`, sont conservés). */
export function filtersToParams(filters, params) {
  const next = new URLSearchParams(params)
  const set = (key, value) => (value == null ? next.delete(key) : next.set(key, String(value)))
  set('joueurs', filters.players)
  set('nombre', filters.players ? FITS.find(f => f.value === filters.fit)?.url ?? null : null)
  set('duree', filters.maxTime)
  set('poids', WEIGHTS.find(w => w.value === filters.weight)?.url ?? null)
  set('mode', filters.coop === true ? 'coop' : filters.coop === false ? 'competitif' : null)
  set('age', filters.age)
  set('joue', filters.played === true ? 'oui' : filters.played === false ? 'non' : null)
  return next
}

/** Critères pour l'API (`played` en est exclu quand `withPlayed` est faux : c'est alors un onglet). */
export function filtersToApi(filters, { withPlayed = true } = {}) {
  return {
    players: filters.players ?? undefined,
    fit: filters.players ? filters.fit ?? undefined : undefined,
    maxTime: filters.maxTime ?? undefined,
    weight: filters.weight ?? undefined,
    coop: filters.coop ?? undefined,
    age: filters.age ?? undefined,
    played: withPlayed ? filters.played ?? undefined : undefined,
  }
}

export function activeCount(filters, { withPlayed = true } = {}) {
  return Object.entries(filters).filter(([key, value]) => value != null && (withPlayed || key !== 'played') && !(key === 'fit' && !filters.players)).length
}

export const sameFilters = (a, b) => Object.keys(NO_FILTERS).every(key => (a[key] ?? null) === (b[key] ?? null))

/** Étiquettes « inconnu » d'un jeu : il reste dans la liste, mais on dit sur quel filtre on ne sait rien de lui. */
export function unknownFields(game, filters) {
  const unknown = []
  if (filters.maxTime && !(game.playingTime > 0)) unknown.push('Durée inconnue')
  if (filters.weight && !(game.complexity > 0)) unknown.push('Difficulté inconnue')
  if (filters.age && game.minAge == null) unknown.push('Âge inconnu')
  if (filters.coop != null && (game.mechanics ?? []).length === 0) unknown.push('Mode inconnu')
  if (filters.players && filters.fit === 'best' && game.bestPlayers == null) unknown.push('Idéal inconnu')
  if (filters.players && filters.fit === 'recommended' && game.recommendedPlayers == null) unknown.push('Avis inconnu')
  return unknown
}

/** « Idéal à 4 » ou « Conseillé à 4 » d'après le sondage BGG, pour le nombre de joueurs choisi ; null sinon. */
export function fitBadge(game, filters) {
  if (!filters.players) return null
  if (game.bestPlayers?.includes(filters.players)) return `Idéal à ${filters.players}`
  if (game.recommendedPlayers?.includes(filters.players)) return `Conseillé à ${filters.players}`
  return null
}
