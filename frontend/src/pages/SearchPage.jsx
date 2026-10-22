import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { searchGames } from '../api'
import GameCard from '../components/GameCard'
import { ENGINES, ENGINE_COLORS, familyLabel } from '../utils/engelstein'
import { FILTER_THEMES, FILTER_TYPES, categoryInfo } from '../utils/categories'

function useDebounce(value, delay) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

const ENGINE_ICONS = {
  worker_placement: '🏗️',
  deck_building:    '🃏',
  engine_building:  '⚙️',
  area_control:     '🗺️',
  hand_management:  '✋',
  auction:          '🔨',
}

/** Trie une liste : possédés en tête, puis par note BGG décroissante */
function sortResults(games) {
  return [...games].sort((a, b) => {
    if (a.owned !== b.owned) return a.owned ? -1 : 1
    return (b.ratingBgg ?? 0) - (a.ratingBgg ?? 0)
  })
}

/** Onglets de résultats avec compteurs et badge "possédés" */
function ResultTabs({ baseGames, expansions, activeTab, onTab }) {
  const hasExpansions = expansions.length > 0

  if (!hasExpansions) return null   // une seule catégorie → pas d'onglets

  const tabs = [
    { key: 'base', label: 'Jeux de base', count: baseGames.length, icon: '🎲' },
    { key: 'expansions', label: 'Extensions', count: expansions.length, icon: '🧩' },
  ]

  return (
    <div className="flex gap-1 mb-4 border-b border-stone-200">
      {tabs.map(tab => (
        <button
          key={tab.key}
          onClick={() => onTab(tab.key)}
          className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px ${
            activeTab === tab.key
              ? 'border-amber-500 text-amber-700'
              : 'border-transparent text-stone-500 hover:text-stone-700'
          }`}
        >
          <span>{tab.icon}</span>
          <span>{tab.label}</span>
          <span className={`ml-1 text-xs px-1.5 py-0.5 rounded-full font-bold ${
            activeTab === tab.key ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-500'
          }`}>
            {tab.count}
          </span>
        </button>
      ))}
    </div>
  )
}

export default function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const [query, setQuery]               = useState(searchParams.get('q') || '')
  const [selectedEngines, setSelectedEngines]     = useState([])
  const [selectedCategory, setSelectedCategory]   = useState(null)
  const [results, setResults]           = useState([])
  const [loading, setLoading]           = useState(false)
  const [searched, setSearched]         = useState(false)
  const [error, setError]               = useState(null)
  const [activeTab, setActiveTab]       = useState('base')  // 'base' | 'expansions'

  const debouncedQuery = useDebounce(query, 400)

  const doSearch = useCallback(async (q, engines, category) => {
    const hasQuery    = q.trim().length > 0
    const hasEngines  = engines.length > 0
    const hasCategory = !!category

    if (!hasQuery && !hasEngines && !hasCategory) {
      setResults([])
      setSearched(false)
      return
    }

    setLoading(true)
    setError(null)
    try {
      const data = await searchGames({ q: q.trim() || undefined, engines, category: category || undefined })
      setResults(data)
      setSearched(true)
      setActiveTab('base')
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    doSearch(debouncedQuery, selectedEngines, selectedCategory)
    if (debouncedQuery) {
      setSearchParams({ q: debouncedQuery }, { replace: true })
    }
  }, [debouncedQuery, selectedEngines, selectedCategory, doSearch, setSearchParams])

  const toggleEngine = (key) =>
    setSelectedEngines(prev =>
      prev.includes(key) ? prev.filter(e => e !== key) : [...prev, key]
    )

  const toggleCategory = (cat) =>
    setSelectedCategory(prev => prev === cat ? null : cat)

  // Séparation jeux de base / extensions, triés par possession puis note
  const baseGames  = sortResults(results.filter(g => !g.isExpansion))
  const expansions = sortResults(results.filter(g =>  g.isExpansion))

  const visibleGames = activeTab === 'expansions' ? expansions : baseGames
  const hasExpansions = expansions.length > 0

  const ownedCount = visibleGames.filter(g => g.owned).length
  const browsingByEngine = !query.trim() && selectedEngines.length > 0

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <h2 className="text-3xl font-black text-stone-900 mb-6">Rechercher un jeu</h2>

      {/* Barre de recherche */}
      <div className="relative mb-5">
        <input
          type="search"
          autoFocus
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Nom du jeu… ex: Azul, Catan, Pandemic"
          className="w-full px-5 py-4 text-lg rounded-2xl border-2 border-stone-300 focus:outline-none focus:border-amber-400 bg-white shadow-sm"
        />
        {loading && (
          <div className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
        )}
      </div>

      {/* Filtres — visibles quand pas de texte */}
      {!query.trim() && (
        <div className="mb-6 space-y-5">
          {/* Moteurs Engelstein */}
          <div>
            <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">
              Parcourir par style de jeu
            </p>
            <div className="flex flex-wrap gap-2">
              {Object.entries(ENGINES).map(([key]) => {
                const active = selectedEngines.includes(key)
                return (
                  <button key={key} onClick={() => toggleEngine(key)}
                    className={
                      'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all ' +
                      (active ? ENGINE_COLORS[key] : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-stone-100')
                    }
                  >
                    <span>{ENGINE_ICONS[key]}</span>
                    <span>{familyLabel(key)}</span>
                  </button>
                )
              })}
            </div>
          </div>

          {/* Thèmes & univers */}
          <div>
            <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">
              🌍 Ou parcourir par thème
            </p>
            <div className="flex flex-wrap gap-1.5 mb-3">
              {FILTER_THEMES.map(cat => {
                const { label, emoji } = categoryInfo(cat)
                const active = selectedCategory === cat
                return (
                  <button key={cat} onClick={() => toggleCategory(cat)}
                    className={
                      'flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ' +
                      (active
                        ? 'bg-amber-500 text-white border-amber-500'
                        : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-amber-50 hover:border-amber-300')
                    }
                  >
                    <span>{emoji}</span><span>{label}</span>
                  </button>
                )
              })}
            </div>
            <div className="flex flex-wrap gap-1.5">
              {FILTER_TYPES.map(cat => {
                const { label, emoji } = categoryInfo(cat)
                const active = selectedCategory === cat
                return (
                  <button key={cat} onClick={() => toggleCategory(cat)}
                    className={
                      'flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ' +
                      (active
                        ? 'bg-blue-500 text-white border-blue-500'
                        : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-blue-50 hover:border-blue-300')
                    }
                  >
                    <span>{emoji}</span><span>{label}</span>
                  </button>
                )
              })}
            </div>
          </div>
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-4">{error}</div>
      )}

      {/* État vide */}
      {!query.trim() && selectedEngines.length === 0 && (
        <div className="text-center py-12 text-stone-400">
          <div className="text-5xl mb-4">🔍</div>
          <p>Tapez un nom de jeu ou choisissez un style ci-dessus</p>
          <p className="text-sm mt-2">Les jeux sont recherchés localement, puis sur BGG si nécessaire</p>
        </div>
      )}

      {searched && results.length === 0 && !loading && (
        <div className="text-center py-12 text-stone-500">
          <div className="text-5xl mb-4">🤷</div>
          {query.trim()
            ? <p className="font-medium">Aucun résultat pour "{query}"</p>
            : <p className="font-medium">Aucun jeu avec ce(s) style(s) dans la base.</p>
          }
          <p className="text-sm mt-2">
            {query.trim()
              ? 'Vérifiez l\'orthographe ou essayez en anglais'
              : 'Importez votre collection ou essayez un autre style'
            }
          </p>
        </div>
      )}

      {results.length > 0 && (
        <div>
          {/* Résumé */}
          <div className="flex items-center justify-between mb-3">
            <p className="text-sm text-stone-400 flex items-center gap-2">
              <span>{results.length} résultat{results.length > 1 ? 's' : ''}</span>
              {ownedCount > 0 && (
                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">
                  {ownedCount} possédé{ownedCount > 1 ? 's' : ''}
                </span>
              )}
              {browsingByEngine && (
                <span className="flex gap-1 flex-wrap">
                  {selectedEngines.map(e => (
                    <span key={e} className={`text-xs px-2 py-0.5 rounded-full border ${ENGINE_COLORS[e]}`}>
                      {familyLabel(e)}
                    </span>
                  ))}
                </span>
              )}
            </p>
          </div>

          {/* Onglets — seulement si des extensions sont présentes */}
          <ResultTabs
            baseGames={baseGames}
            expansions={expansions}
            activeTab={activeTab}
            onTab={setActiveTab}
          />

          {/* Si pas d'extensions : afficher directement les jeux de base sans onglets */}
          {!hasExpansions && baseGames.length === 0 && (
            <p className="text-sm text-stone-400 text-center py-8">Aucun résultat dans cette catégorie</p>
          )}

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            {visibleGames.map(game => (
              <GameCard
                key={game.id}
                game={game}
                owned={game.owned != null ? game.owned : undefined}
              />
            ))}
          </div>

          {/* Indicateur de séparation possédé / non possédé dans l'onglet actif */}
          {visibleGames.some(g => g.owned) && visibleGames.some(g => !g.owned) && (
            <div className="mt-3 pt-3 border-t border-stone-100 text-xs text-stone-400 text-center">
              ↑ dans votre collection · non possédés ↓
            </div>
          )}
        </div>
      )}
    </div>
  )
}
