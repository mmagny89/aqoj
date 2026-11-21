// Niveaux de difficulté (poids BGG) partagés par l'accueil et « À quoi on joue » ; le choix est mémorisé dans le navigateur.
export const DIFFICULTIES = [
  { label: 'Toutes', value: null },
  { label: 'Léger', value: 'light' },
  { label: 'Intermédiaire', value: 'medium' },
  { label: 'Costaud', value: 'heavy' },
]
const DIFFICULTY_KEY = 'aqoj_difficulte'

export function storedDifficulty() {
  try {
    const v = localStorage.getItem(DIFFICULTY_KEY)
    return DIFFICULTIES.some(d => d.value === v) ? v : null
  } catch {
    return null
  }
}

export function storeDifficulty(value) {
  try {
    if (value) localStorage.setItem(DIFFICULTY_KEY, value)
    else localStorage.removeItem(DIFFICULTY_KEY)
  } catch { /* stockage indisponible : le choix ne sera pas retenu */ }
}
