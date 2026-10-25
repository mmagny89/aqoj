import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { searchGames } from '../api'
import GameCard from '../components/GameCard'
import { ENGINES, familyLabel } from '../utils/engelstein'
import { FILTER_THEMES, FILTER_TYPES, categoryInfo } from '../utils/categories'

function useDebounce(value, delay) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

/** Trie une liste : possédés en tête, puis par note BGG décroissante */
function sortResults(games) {
  return [...games].sort((a, b) => {
    if (a.owned !== b.owned) return a.owned ? -1 : 1
    return (b.ratingBgg ?? 0) - (a.ratingBgg ?? 0)
  })
}

/** Onglets de résultats avec compteurs : seulement si des extensions sont présentes. */
function ResultTabs({ baseGames, expansions, activeTab, onTab }) {
  if (expansions.length === 0) return null

  const tabs = [
    { key: 'base', label: 'Jeux de base', count: baseGames.length },
    { key: 'expansions', label: 'Extensions', count: expansions.length },
  ]

  return (
    <div role="tablist" aria-label="Type de résultats" className="mb-5 flex flex-wrap gap-2">
      {tabs.map(tab => (
        <button
          key={tab.key}
          type="button"
          role="tab"
          id={`resultats-${tab.key}`}
          aria-selected={activeTab === tab.key}
          aria-controls="resultats-panneau"
          onClick={() => onTab(tab.key)}
          className={`chip ${activeTab === tab.key ? 'is-on' : ''}`}
        >
          {tab.label}
          <span className="ml-2 text-sm opacity-80">{tab.count}</span>
        </button>
      ))}
    </div>
  )
}

function Chip({ active, onClick, children }) {
  return (
    <button type="button" className="chip" aria-pressed={active} onClick={onClick}>
      {children}
    </button>
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
    <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div>
        <h1 className="text-4xl leading-tight sm:text-5xl">Explorer</h1>
        <p className="mt-1 text-lg text-stone-600">Par son nom, par style de jeu ou par thème.</p>
      </div>

      {/* Recherche */}
      <form role="search" onSubmit={e => e.preventDefault()} className="relative">
        <label htmlFor="explorer-q" className="sr-only">Nom du jeu</label>
        <input
          id="explorer-q"
          type="search"
          autoFocus
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Nom du jeu : Azul, Catan, Pandemic…"
          className="min-h-14 w-full rounded-2xl border-[3px] border-ink bg-paper px-5 text-lg shadow-token-sm outline-none placeholder:text-stone-400 focus-visible:outline-4 focus-visible:outline-offset-3 focus-visible:outline-brand-blue"
        />
        {loading && (
          <span role="status" className="absolute right-4 top-1/2 -translate-y-1/2">
            <span className="block h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
            <span className="sr-only">Recherche en cours</span>
          </span>
        )}
      </form>

      {/* Filtres : visibles tant qu'aucun texte n'est saisi */}
      {!query.trim() && (
        <div className="panel flex flex-col gap-6 p-5 sm:p-6">
          <section aria-labelledby="f-styles">
            <h2 id="f-styles" className="mb-3 text-2xl">Parcourir par style de jeu</h2>
            <div className="flex flex-wrap gap-2">
              {Object.keys(ENGINES).map(key => (
                <Chip key={key} active={selectedEngines.includes(key)} onClick={() => toggleEngine(key)}>
                  {familyLabel(key)}
                </Chip>
              ))}
            </div>
          </section>

          <section aria-labelledby="f-themes">
            <h2 id="f-themes" className="mb-3 text-2xl">Ou par thème</h2>
            <div className="mb-3 flex flex-wrap gap-2">
              {FILTER_THEMES.map(cat => {
                const { label, emoji } = categoryInfo(cat)
                return (
                  <Chip key={cat} active={selectedCategory === cat} onClick={() => toggleCategory(cat)}>
                    {emoji && <span aria-hidden="true" className="mr-1.5">{emoji}</span>}{label}
                  </Chip>
                )
              })}
            </div>
            <div className="flex flex-wrap gap-2">
              {FILTER_TYPES.map(cat => {
                const { label, emoji } = categoryInfo(cat)
                return (
                  <Chip key={cat} active={selectedCategory === cat} onClick={() => toggleCategory(cat)}>
                    {emoji && <span aria-hidden="true" className="mr-1.5">{emoji}</span>}{label}
                  </Chip>
                )
              })}
            </div>
          </section>
        </div>
      )}

      {error && (
        <p role="alert" className="panel border-brand-red bg-amber-100 p-4 font-bold">{error}</p>
      )}

      {/* État vide */}
      {!query.trim() && selectedEngines.length === 0 && !selectedCategory && (
        <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
          <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
          <p className="font-display text-2xl">Tapez un nom ou choisissez un style</p>
          <p className="text-stone-600">Les jeux sont cherchés dans la base, puis sur BGG si besoin.</p>
        </section>
      )}

      {searched && results.length === 0 && !loading && (
        <section className="panel mx-auto max-w-xl p-8 text-center">
          <h2 className="text-2xl">
            {query.trim() ? `Aucun résultat pour « ${query} »` : 'Aucun jeu avec ce filtre'}
          </h2>
          <p className="mt-2 text-stone-600">
            {query.trim()
              ? "Vérifiez l'orthographe ou essayez le titre en anglais."
              : 'Importez votre collection ou essayez un autre style.'}
          </p>
        </section>
      )}

      {results.length > 0 && (
        <section aria-label="Résultats">
          <p className="mb-4 flex flex-wrap items-center gap-2 text-stone-600" aria-live="polite">
            <span className="font-bold text-ink">{results.length} résultat{results.length > 1 ? 's' : ''}</span>
            {ownedCount > 0 && (
              <span className="badge bg-brand-green text-paper">{ownedCount} possédé{ownedCount > 1 ? 's' : ''}</span>
            )}
            {browsingByEngine && selectedEngines.map(e => (
              <span key={e} className="badge bg-cream">{familyLabel(e)}</span>
            ))}
          </p>

          <ResultTabs baseGames={baseGames} expansions={expansions} activeTab={activeTab} onTab={setActiveTab} />

          {!hasExpansions && baseGames.length === 0 && (
            <p className="py-8 text-center text-stone-600">Aucun résultat dans cette catégorie.</p>
          )}

          <ul
            id="resultats-panneau"
            role={hasExpansions ? 'tabpanel' : undefined}
            aria-labelledby={hasExpansions ? `resultats-${activeTab}` : undefined}
            className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 2xl:grid-cols-5"
          >
            {visibleGames.map(game => (
              <li key={game.id}>
                <GameCard game={game} owned={game.owned != null ? game.owned : undefined} />
              </li>
            ))}
          </ul>

          {visibleGames.some(g => g.owned) && visibleGames.some(g => !g.owned) && (
            <p className="mt-4 text-center text-sm text-stone-600">
              Les jeux de votre collection viennent en premier.
            </p>
          )}
        </section>
      )}
    </div>
  )
}
