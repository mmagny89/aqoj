import { useState, useEffect } from 'react'
import { getThemeMappings } from '../api'

/**
 * Cache module-level : un seul fetch pour toute la session.
 * Contient null jusqu'au premier chargement, puis le tableau de mappings.
 */
let _cache = null
let _promise = null

/**
 * Hook — retourne les mappings BGG → thème depuis la base de données.
 *
 * Retourne :
 *   mappings   : ThemeMapping[]  (tous)
 *   byBgg      : Map<bggCategory, ThemeMapping>
 *   groups     : Map<groupKey, ThemeMapping[]>
 *   groupMeta  : Map<groupKey, {label, emoji}>
 *   loading    : bool
 */
export function useThemeMappings() {
  const [mappings, setMappings] = useState(_cache ?? [])
  const [loading, setLoading]   = useState(_cache === null)

  useEffect(() => {
    if (_cache !== null) {
      setMappings(_cache)
      setLoading(false)
      return
    }
    if (!_promise) {
      _promise = getThemeMappings().then(data => { _cache = data; return data })
    }
    _promise.then(data => {
      setMappings(data)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [])

  const byBgg = new Map(mappings.map(m => [m.bggCategory, m]))

  const groups = new Map()
  for (const m of mappings) {
    if (!groups.has(m.themeGroup)) groups.set(m.themeGroup, [])
    groups.get(m.themeGroup).push(m)
  }

  return { mappings, byBgg, groups, loading }
}

/** Labels et emojis des groupes (ordre d'affichage). */
export const GROUP_META = {
  universe:   { label: 'Univers & fiction',         emoji: '🌌' },
  historical: { label: 'Histoire',                  emoji: '⚔️' },
  game_type:  { label: 'Type de jeu',               emoji: '🎯' },
  society:    { label: 'Société & monde réel',      emoji: '🌍' },
  culture:    { label: 'Culture & divertissement',  emoji: '🎭' },
}

export const GROUP_ORDER = ['universe', 'historical', 'game_type', 'society', 'culture']

/** Invalide le cache (utile après une modification admin). */
export function invalidateThemeCache() {
  _cache = null
  _promise = null
}
