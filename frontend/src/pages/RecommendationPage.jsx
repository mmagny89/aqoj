import { useState, useEffect, useId, useRef } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { getRecommendations, getUserPreferences, getThemeMappings } from '../api'
import GameCard from '../components/GameCard'
import { useAuth } from '../context/AuthContext'
import { ENGINES, SUPPORT_FAMILIES, EXTRA_FAMILIES, familyLabel } from '../utils/engelstein'

/* ── Chip sélectionnable ─────────────────────────────────────────────── */
function Chip({ active, onClick, icon, label, small = false }) {
  return (
    <button type="button" onClick={onClick} aria-pressed={active} className={small ? 'chip chip-sm' : 'chip !min-h-11 text-sm'}>
      {small && <span aria-hidden="true" className="mr-1 inline-block w-3 text-center">{active ? '✓' : ''}</span>}
      {icon && <span aria-hidden="true" className="mr-1.5">{icon}</span>}
      {label}
    </button>
  )
}

/* ── Section filtres (repliable) ─────────────────────────────────────── */
function FilterSection({ title, children, defaultOpen = true }) {
  const [open, setOpen] = useState(defaultOpen)
  const id = useId()
  return (
    <div className="border-b-2 border-ink/15 pb-4">
      <button
        type="button"
        onClick={() => setOpen(v => !v)}
        aria-expanded={open}
        aria-controls={id}
        className="mb-1 flex min-h-11 w-full items-center justify-between text-left font-bold"
      >
        <span>{title}</span>
        <span aria-hidden="true" className="text-stone-600">{open ? '−' : '+'}</span>
      </button>
      <div id={id} hidden={!open}>{children}</div>
    </div>
  )
}

/* ── Grille de résultats avec sous-onglets base / extension ─────────── */
function ResultsGrid({ results, loading, error }) {
  const [sub, setSub] = useState('base')

  const base = (results ?? []).filter(r => !r.game.isExpansion)
  const exps  = (results ?? []).filter(r =>  r.game.isExpansion)
  const items = sub === 'ext' ? exps : base

  // Au premier chargement seulement : ensuite les anciens résultats restent affichés pendant l'actualisation.
  if (loading && results === null) return (
    <p role="status" className="flex h-32 items-center justify-center gap-3 text-stone-600">
      <span className="h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
      Chargement…
    </p>
  )

  if (error) return <p role="alert" className="panel border-brand-red bg-amber-100 p-4 font-bold">{error}</p>

  return (
    <div aria-busy={loading} className={loading ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
      <div role="group" aria-label="Type de fiches" className="mb-4 flex flex-wrap gap-1.5">
        {[
          { key: 'base', label: 'Jeux de base', count: base.length },
          { key: 'ext',  label: 'Extensions',   count: exps.length },
        ].map(t => (
          <button
            key={t.key}
            type="button"
            aria-pressed={sub === t.key}
            onClick={() => setSub(t.key)}
            disabled={t.count === 0}
            className="chip chip-sm disabled:cursor-default disabled:opacity-40"
          >
            {t.label}
            <span className="ml-2 text-sm opacity-80">{t.count}</span>
          </button>
        ))}
      </div>

      {items.length === 0 ? (
        <p className="panel mx-auto max-w-xl p-6 text-center text-stone-700">
          {sub === 'ext'
            ? 'Aucune extension ne correspond à ces critères.'
            : 'Aucun jeu de base ne correspond exactement.'}
        </p>
      ) : (
        <>
          <p className="mb-3 text-stone-600" aria-live="polite">{items.length} jeu{items.length > 1 ? 'x' : ''}</p>
          <ul className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 sm:gap-5 xl:grid-cols-4">
            {items.map(({ game, reason }, i) => (
              <li key={game.id}><GameCard game={game} reason={reason} rank={i + 1} /></li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}

/* ── Panneau résultats : trois périmètres, chacun avec son compte ───── */
const SCOPE_TABS = [
  { key: 'collection', label: 'Dans ma ludothèque' },
  { key: 'library',    label: 'À l’association' },
  { key: 'discover',   label: 'Tout le catalogue' },
]

function ResultsPanel({ results, activeTab, onTabChange }) {
  return (
    <div>
      <div role="tablist" aria-label="Où chercher" className="tab-list mb-5">
        {SCOPE_TABS.map(tab => (
          <button
            key={tab.key}
            type="button"
            role="tab"
            id={`reco-${tab.key}`}
            aria-selected={activeTab === tab.key}
            aria-controls="reco-panneau"
            onClick={() => onTabChange(tab.key)}
            className="tab"
          >
            {tab.label}
            <span className="ml-2 text-sm opacity-80">{results[tab.key].data?.length ?? '…'}</span>
          </button>
        ))}
      </div>

      <div id="reco-panneau" role="tabpanel" aria-labelledby={`reco-${activeTab}`}>
        <ResultsGrid results={results[activeTab].data} loading={results[activeTab].loading} error={results[activeTab].error} />
      </div>
    </div>
  )
}

const EMPTY_RESULTS = Object.fromEntries(SCOPE_TABS.map(t => [t.key, { data: null, loading: false, error: null }]))

/* ── Page principale ─────────────────────────────────────────────────── */
export default function RecommendationPage() {
  const { user } = useAuth()
  // L'accueil envoie `?joueurs=3&duree=60` : la recherche part d'elle-même avec ces valeurs.
  const [searchParams] = useSearchParams()
  const fromHome = searchParams.has('joueurs')
  const positive = (key, fallback) => {
    const value = Number(searchParams.get(key))
    return Number.isInteger(value) && value > 0 ? value : fallback
  }

  const [players,            setPlayers]            = useState(() => positive('joueurs', 4))
  const [maxTime,            setMaxTime]            = useState(() => positive('duree', 60))
  const [selectedFamilies,   setSelectedFamilies]   = useState([])
  const [selectedCategories, setSelectedCategories] = useState([])
  const [results,            setResults]            = useState(EMPTY_RESULTS)
  const [activeTab,          setActiveTab]          = useState('collection')
  const [ready,              setReady]              = useState(false)
  const [filtersOpen,        setFiltersOpen]        = useState(false)
  const [themeGroups,        setThemeGroups]        = useState({ themes: [], types: [] })
  // Map label → liste de bggCategories (pour l'expansion lors de la soumission)
  const [themeLabelToCats,   setThemeLabelToCats]   = useState({})

  // Charger les préférences et les thèmes admin
  useEffect(() => {
    if (user) {
      getUserPreferences().then(prefs => {
        // Point de départ : la table enregistrée (sauf si l'adresse en porte une) et les styles favoris, goûts déclarés d'abord.
        if (prefs?.table && !fromHome) {
          setPlayers(prefs.table.players)
          setMaxTime(prefs.table.maxTime)
        }
        const favorites = [...new Set([...(prefs?.declaredFamilies ?? []), ...(prefs?.topEngines ?? [])])]
        setSelectedFamilies(favorites)
      }).catch(() => {}).finally(() => setReady(true))
    }
    getThemeMappings().then(mappings => {
      // Construire label → [bggCategories] + deux listes triées
      const labelMap   = {}   // label → { group, emoji, bggCategories[] }
      for (const m of mappings) {
        if (!labelMap[m.themeLabel]) {
          labelMap[m.themeLabel] = { group: m.themeGroup, emoji: m.themeEmoji, bggCategories: [] }
        }
        if (!labelMap[m.themeLabel].bggCategories.includes(m.bggCategory)) {
          labelMap[m.themeLabel].bggCategories.push(m.bggCategory)
        }
      }
      const themes = []
      const types  = []
      for (const [label, data] of Object.entries(labelMap)) {
        const item = { label, emoji: data.emoji, bggCategories: data.bggCategories }
        if (data.group === 'game_type') types.push(item)
        else themes.push(item)
      }
      themes.sort((a, b) => a.label.localeCompare(b.label, 'fr'))
      types.sort((a, b) => a.label.localeCompare(b.label, 'fr'))
      setThemeGroups({ themes, types })
      // Mémorise le mapping label → bggCategories pour la soumission
      const catMap = {}
      for (const [label, data] of Object.entries(labelMap)) catMap[label] = data.bggCategories
      setThemeLabelToCats(catMap)
    }).catch(() => {})
  }, [user, fromHome])

  const toggle    = (key)   => setSelectedFamilies(prev   => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key])
  const toggleCat = (label) => setSelectedCategories(prev => prev.includes(label) ? prev.filter(l => l !== label) : [...prev, label])
  const clearAll  = () => { setSelectedFamilies([]); setSelectedCategories([]) }

  // Résultats en direct : tout changement de critère relance les trois recherches (légère attente pour le curseur de durée).
  const firstLoad = useRef(true)
  useEffect(() => {
    if (!user || !ready) return
    let current = true
    const expandedCats = selectedCategories.flatMap(label => themeLabelToCats[label] ?? [])
    const params = { players, maxTime, families: selectedFamilies, categories: expandedCats }

    const timer = setTimeout(() => {
      const settled = {}
      SCOPE_TABS.forEach(({ key }) => {
        setResults(prev => ({ ...prev, [key]: { ...prev[key], loading: true, error: null } }))
        getRecommendations({ ...params, scope: key })
          .then(data => { settled[key] = data; if (current) setResults(prev => ({ ...prev, [key]: { data, loading: false, error: null } })) })
          .catch(err => { settled[key] = []; if (current) setResults(prev => ({ ...prev, [key]: { data: prev[key].data, loading: false, error: err.message || 'Erreur de chargement' } })) })
          .finally(() => {
            // À la première arrivée, on ouvre le premier onglet qui a quelque chose à montrer.
            if (!current || !firstLoad.current || Object.keys(settled).length < SCOPE_TABS.length) return
            firstLoad.current = false
            const first = SCOPE_TABS.find(t => settled[t.key]?.length > 0)
            if (first) setActiveTab(first.key)
          })
      })
    }, 250)

    return () => { current = false; clearTimeout(timer) }
  }, [user, ready, players, maxTime, selectedFamilies, selectedCategories, themeLabelToCats])

  if (!user) return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
      <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
        <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
        <h1 className="text-4xl">Connexion requise</h1>
        <p className="text-stone-600">Les recommandations tiennent compte de votre ludothèque et de vos goûts.</p>
        <Link to="/connexion" className="btn btn-primary">Se connecter</Link>
      </section>
    </div>
  )

  const activeFiltersCount = selectedFamilies.length + selectedCategories.length

  return (
    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-10 lg:py-8">

      <div className="mb-6">
        <h1 className="text-4xl leading-tight sm:text-5xl">À quoi on joue…</h1>
        <p className="mt-1 text-lg text-stone-600">Selon vos styles favoris et votre table : les résultats se mettent à jour à chaque changement.</p>
      </div>

      <div>
        <div className="flex flex-col lg:flex-row gap-6 lg:items-start">

          {/* ── Panneau filtres (sidebar) ─────────────────────────────── */}
          <aside className={`
            w-full lg:w-72 flex-shrink-0 panel p-5 space-y-4
            ${filtersOpen ? 'block' : 'hidden lg:block'}
          `}>
            <div className="flex items-center justify-between">
              <h2 className="text-2xl">Filtres</h2>
              {activeFiltersCount > 0 && (
                <button type="button" onClick={clearAll} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">
                  Réinitialiser ({activeFiltersCount})
                </button>
              )}
            </div>

            {/* Joueurs */}
            <FilterSection title="Nombre de joueurs">
              <div className="flex flex-wrap gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8].map(n => (
                  <button
                    key={n} type="button" onClick={() => setPlayers(n)} aria-pressed={players === n}
                    className="chip min-w-11 justify-center !px-0"
                  >{n}</button>
                ))}
              </div>
            </FilterSection>

            {/* Durée */}
            <FilterSection title="Durée maximum">
              <div className="mb-2 text-center">
                <span className="font-display text-3xl">
                  {maxTime < 60 ? `${maxTime} min` : `${Math.floor(maxTime / 60)}h${maxTime % 60 ? maxTime % 60 + 'm' : ''}`}
                </span>
              </div>
              <input
                type="range" min={15} max={240} step={15} value={maxTime} aria-label="Durée maximum, en minutes"
                onChange={e => setMaxTime(Number(e.target.value))}
                className="h-11 w-full accent-brand-red"
              />
              <div className="mt-1 flex justify-between text-sm text-stone-600">
                <span>15m</span><span>1h</span><span>2h</span><span>3h</span><span>4h</span>
              </div>
            </FilterSection>

            {/* Moteurs Engelstein */}
            <FilterSection title="Style / Moteur">
              <div className="flex flex-wrap gap-1.5">
                {Object.keys(ENGINES).map(key => (
                  <Chip
                    key={key}
                    active={selectedFamilies.includes(key)}
                    onClick={() => toggle(key)}
                    label={familyLabel(key)}
                  />
                ))}
              </div>
            </FilterSection>

            {/* Mécaniques de support */}
            <FilterSection title="Mécaniques" defaultOpen={false}>
              <div className="flex flex-wrap gap-1.5">
                {Object.keys(SUPPORT_FAMILIES).map(key => (
                  <Chip
                    key={key}
                    active={selectedFamilies.includes(key)}
                    onClick={() => toggle(key)}
                    label={familyLabel(key)}
                  />
                ))}
              </div>
            </FilterSection>

            {/* Caractéristiques */}
            <FilterSection title="Caractéristiques" defaultOpen={false}>
              <div className="flex flex-wrap gap-1.5">
                {Object.keys(EXTRA_FAMILIES).map(key => (
                  <Chip
                    key={key}
                    active={selectedFamilies.includes(key)}
                    onClick={() => toggle(key)}
                    label={familyLabel(key)}
                  />
                ))}
              </div>
            </FilterSection>

            {/* Thèmes & univers (mappés en admin) */}
            {themeGroups.themes.length > 0 && (
              <FilterSection title="Thèmes & univers" defaultOpen={false}>
                <div className="flex flex-wrap gap-1.5">
                  {themeGroups.themes.map(item => (
                    <Chip
                      key={item.label}
                      active={selectedCategories.includes(item.label)}
                      onClick={() => toggleCat(item.label)}
                      icon={item.emoji}
                      label={item.label}
                    />
                  ))}
                </div>
              </FilterSection>
            )}

            {/* Types de jeu (mappés en admin) */}
            {themeGroups.types.length > 0 && (
              <FilterSection title="Types de jeu" defaultOpen={false}>
                <div className="flex flex-wrap gap-1.5">
                  {themeGroups.types.map(item => (
                    <Chip
                      key={item.label}
                      active={selectedCategories.includes(item.label)}
                      onClick={() => toggleCat(item.label)}
                      icon={item.emoji}
                      label={item.label}
                    />
                  ))}
                </div>
              </FilterSection>
            )}
          </aside>

          {/* ── Bouton mobile toggle filtres ─────────────────────────── */}
          <div className="order-first lg:hidden w-full">
            <button
              type="button"
              onClick={() => setFiltersOpen(v => !v)}
              aria-expanded={filtersOpen}
              className="w-full min-h-12 flex items-center justify-between px-4 panel text-base font-bold mb-0"
            >
              <span>Filtres {activeFiltersCount > 0 && `(${activeFiltersCount})`}</span>
              <span aria-hidden="true">{filtersOpen ? '−' : '+'}</span>
            </button>
          </div>

          {/* ── Zone résultats ────────────────────────────────────────── */}
          <div className="flex-1 min-w-0">
            <ResultsPanel results={results} activeTab={activeTab} onTabChange={setActiveTab} />
          </div>

        </div>
      </div>
    </div>
  )
}
