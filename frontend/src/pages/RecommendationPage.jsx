import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getRecommendations, getUserPreferences, getThemeMappings } from '../api'
import GameCard from '../components/GameCard'
import { useAuth } from '../context/AuthContext'
import { ENGINES, SUPPORT_FAMILIES, EXTRA_FAMILIES, familyLabel, familyColor, SUPPORT_COLOR, EXTRA_COLORS } from '../utils/engelstein'

const ENGINE_ICONS = {
  worker_placement: '🏗️', deck_building: '🃏', engine_building: '⚙️',
  area_control: '🗺️', hand_management: '✋', auction: '🔨',
}
const SUPPORT_ICONS = {
  card_play: '🂠', spatial_placement: '🧩', movement: '🚶',
  resource_management: '💰', resolution: '🎲', uncertainty: '🎭',
  cooperation: '🤝', scoring: '🏆',
}
const EXTRA_ICONS = { solo: '🧍', real_time: '⏰', dexterity: '🤸', legacy: '📖' }

/* ── Chip sélectionnable ─────────────────────────────────────────────── */
function Chip({ active, onClick, icon, label, colorClass }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all ${
        active
          ? (colorClass || 'bg-amber-500 text-white border-amber-500')
          : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-stone-100'
      }`}
    >
      {icon && <span>{icon}</span>}
      <span>{label}</span>
    </button>
  )
}

/* ── Section filtres (collapsible) ───────────────────────────────────── */
function FilterSection({ title, children, defaultOpen = true }) {
  const [open, setOpen] = useState(defaultOpen)
  return (
    <div className="border-b border-stone-100 pb-4">
      <button
        type="button"
        onClick={() => setOpen(v => !v)}
        className="flex items-center justify-between w-full text-xs font-semibold text-stone-500 uppercase tracking-wide mb-2"
      >
        <span>{title}</span>
        <span className="text-stone-300">{open ? '▲' : '▼'}</span>
      </button>
      {open && children}
    </div>
  )
}

/* ── Grille de résultats avec sous-onglets base / extension ─────────── */
function ResultsGrid({ results, loading, error }) {
  const [sub, setSub] = useState('base')

  const base = results.filter(r => !r.game.isExpansion)
  const exps  = results.filter(r =>  r.game.isExpansion)
  const items = sub === 'ext' ? exps : base

  if (loading) return (
    <div className="flex items-center justify-center h-32 gap-2 text-stone-400">
      <div className="w-4 h-4 border-2 border-stone-200 border-t-amber-400 rounded-full animate-spin" />
      <span className="text-sm">Chargement…</span>
    </div>
  )

  if (error) return (
    <div className="text-center py-8 text-stone-400 text-sm">
      <div className="text-2xl mb-2">😕</div>{error}
    </div>
  )

  return (
    <div>
      {/* Sous-onglets toujours affichés */}
      <div className="flex gap-2 mb-4">
        {[
          { key: 'base', label: '🎲 Jeux de base', count: base.length },
          { key: 'ext',  label: '➕ Extensions',   count: exps.length },
        ].map(t => (
          <button
            key={t.key}
            onClick={() => setSub(t.key)}
            disabled={t.count === 0}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all disabled:opacity-40 disabled:cursor-default ${
              sub === t.key
                ? 'bg-amber-500 text-white border-amber-500'
                : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50'
            }`}
          >
            {t.label}
            <span className={`px-1.5 py-0.5 rounded-full font-bold text-[10px] ${sub === t.key ? 'bg-white/30 text-white' : 'bg-stone-100 text-stone-500'}`}>
              {t.count}
            </span>
          </button>
        ))}
      </div>

      {items.length === 0 ? (
        <div className="text-center py-10 text-stone-400">
          <div className="text-3xl mb-2">{sub === 'ext' ? '🧩' : '📭'}</div>
          <p className="text-sm">
            {sub === 'ext'
              ? 'Aucune extension ne correspond à ces critères.'
              : 'Aucun jeu de base ne correspond exactement.'}
          </p>
        </div>
      ) : (
        <>
          <p className="text-xs text-stone-400 mb-3">{items.length} jeu{items.length > 1 ? 'x' : ''}</p>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            {items.map(({ game, reason }, i) => (
              <GameCard key={game.id} game={game} reason={reason} rank={i + 1} />
            ))}
          </div>
        </>
      )}
    </div>
  )
}

/* ── Panneau résultats ───────────────────────────────────────────────── */
function ResultsPanel({
  collectionResults, collectionLoading, collectionError,
  discoverResults,   discoverLoading,   discoverError,
  searched,
}) {
  const [activeTab, setActiveTab] = useState('collection')

  // Auto-sélectionner l'onglet le plus fourni une fois les deux chargés
  useEffect(() => {
    if (!collectionLoading && !discoverLoading && collectionResults && discoverResults) {
      if (discoverResults.length > collectionResults.length) setActiveTab('discover')
    }
  }, [collectionLoading, discoverLoading, collectionResults, discoverResults])

  if (!searched) return (
    <div className="flex flex-col items-center justify-center h-64 text-stone-300 gap-3">
      <div className="text-6xl">🎲</div>
      <p className="text-sm">Renseignez vos critères et lancez la recherche</p>
    </div>
  )

  const colCount = collectionResults?.length ?? '…'
  const disCount = discoverResults?.length ?? '…'

  return (
    <div>
      {/* Onglets principaux collection / découverte */}
      <div className="flex gap-1 border-b border-stone-200 mb-5">
        {[
          { key: 'collection', label: '📚 Ma collection', count: colCount },
          { key: 'discover',   label: '🔭 À découvrir',   count: disCount },
        ].map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px ${
              activeTab === tab.key
                ? 'border-amber-500 text-amber-700'
                : 'border-transparent text-stone-500 hover:text-stone-700'
            }`}
          >
            {tab.label}
            <span className={`text-xs px-1.5 py-0.5 rounded-full font-bold ${
              activeTab === tab.key ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-500'
            }`}>{tab.count}</span>
          </button>
        ))}
      </div>

      {activeTab === 'collection' ? (
        <ResultsGrid
          results={collectionResults ?? []}
          loading={collectionLoading}
          error={collectionError}
        />
      ) : (
        <ResultsGrid
          results={discoverResults ?? []}
          loading={discoverLoading}
          error={discoverError}
        />
      )}
    </div>
  )
}

/* ── Page principale ─────────────────────────────────────────────────── */
export default function RecommendationPage() {
  const { user } = useAuth()

  const [players,            setPlayers]            = useState(4)
  const [maxTime,            setMaxTime]            = useState(60)
  const [selectedFamilies,   setSelectedFamilies]   = useState([])
  const [selectedCategories, setSelectedCategories] = useState([])
  const [collectionResults,  setCollectionResults]  = useState(null)
  const [discoverResults,    setDiscoverResults]    = useState(null)
  const [colLoading,         setColLoading]         = useState(false)
  const [disLoading,         setDisLoading]         = useState(false)
  const [colError,           setColError]           = useState(null)
  const [disError,           setDisError]           = useState(null)
  const [searched,           setSearched]           = useState(false)
  const [filtersOpen,        setFiltersOpen]        = useState(false)
  const [userPrefs,          setUserPrefs]          = useState(null)
  const [themeGroups,        setThemeGroups]        = useState({ themes: [], types: [] })
  // Map label → liste de bggCategories (pour l'expansion lors de la soumission)
  const [themeLabelToCats,   setThemeLabelToCats]   = useState({})

  // Charger les préférences et les thèmes admin
  useEffect(() => {
    if (user) getUserPreferences().then(setUserPrefs).catch(() => {})
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
  }, [user])

  const toggle    = (key)   => setSelectedFamilies(prev   => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key])
  const toggleCat = (label) => setSelectedCategories(prev => prev.includes(label) ? prev.filter(l => l !== label) : [...prev, label])
  const clearAll  = () => { setSelectedFamilies([]); setSelectedCategories([]) }

  const handleSubmit = (e) => {
    e?.preventDefault()
    // Expandre les labels sélectionnés en bggCategories concrètes
    const expandedCats = selectedCategories.flatMap(label => themeLabelToCats[label] ?? [])
    const params = { players, maxTime, families: selectedFamilies, categories: expandedCats }

    // Lancer les deux appels en parallèle, indépendamment
    setSearched(true)
    setCollectionResults(null)
    setDiscoverResults(null)
    setColError(null)
    setDisError(null)

    setColLoading(true)
    getRecommendations({ ...params, scope: 'collection' })
      .then(setCollectionResults)
      .catch(err => setColError(err.message || 'Erreur collection'))
      .finally(() => setColLoading(false))

    setDisLoading(true)
    getRecommendations({ ...params, scope: 'discover' })
      .then(setDiscoverResults)
      .catch(err => setDisError(err.message || 'Erreur découverte'))
      .finally(() => setDisLoading(false))
  }

  if (!user) return (
    <div className="max-w-6xl mx-auto px-4 py-16 text-center text-stone-500">
      <div className="text-5xl mb-4">🔒</div>
      <p className="font-semibold text-lg text-stone-800 mb-2">Connexion requise</p>
      <p className="text-sm mb-6">Les recommandations sont personnalisées depuis votre collection BGG.</p>
      <Link to="/connexion" className="bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-3 rounded-xl">Se connecter</Link>
    </div>
  )

  const activeFiltersCount = selectedFamilies.length + selectedCategories.length

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">

      {/* Header */}
      <div className="mb-6">
        <h2 className="text-3xl font-black text-stone-900">Ce soir on joue à…</h2>
        <p className="text-stone-400 text-sm mt-1">Recommandations personnalisées depuis votre collection BGG</p>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="flex gap-6 items-start">

          {/* ── Panneau filtres (sidebar) ─────────────────────────────── */}
          <aside className={`
            w-72 flex-shrink-0 bg-white rounded-2xl border border-stone-200 shadow-sm p-5 space-y-4
            ${filtersOpen ? 'block' : 'hidden lg:block'}
          `}>
            <div className="flex items-center justify-between">
              <h3 className="font-bold text-stone-800">Filtres</h3>
              {activeFiltersCount > 0 && (
                <button type="button" onClick={clearAll} className="text-xs text-stone-400 hover:text-red-500 hover:underline">
                  Réinitialiser ({activeFiltersCount})
                </button>
              )}
            </div>

            {/* Suggestions issues des préférences */}
            {userPrefs?.topEngines?.length > 0 && (
              <div className="bg-amber-50 rounded-xl p-3">
                <p className="text-xs font-semibold text-amber-700 mb-2">✨ Vos styles favoris</p>
                <div className="flex flex-wrap gap-1.5">
                  {userPrefs.topEngines.map(key => (
                    <button
                      key={key}
                      type="button"
                      onClick={() => toggle(key)}
                      className={`text-xs px-2.5 py-1 rounded-full border font-medium transition-all ${
                        selectedFamilies.includes(key) ? familyColor(key) : 'bg-white border-amber-200 text-amber-700 hover:bg-amber-100'
                      }`}
                    >
                      {familyLabel(key)}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Joueurs */}
            <FilterSection title="Nombre de joueurs">
              <div className="flex flex-wrap gap-1.5">
                {[1, 2, 3, 4, 5, 6, 7, 8].map(n => (
                  <button
                    key={n} type="button" onClick={() => setPlayers(n)}
                    className={`w-9 h-9 rounded-lg font-bold text-sm transition-all ${
                      players === n ? 'bg-amber-500 text-white shadow' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'
                    }`}
                  >{n}</button>
                ))}
              </div>
            </FilterSection>

            {/* Durée */}
            <FilterSection title="Durée maximum">
              <div className="text-center mb-2">
                <span className="text-lg font-black text-amber-600">
                  {maxTime < 60 ? `${maxTime} min` : `${Math.floor(maxTime / 60)}h${maxTime % 60 ? maxTime % 60 + 'm' : ''}`}
                </span>
              </div>
              <input
                type="range" min={15} max={240} step={15} value={maxTime}
                onChange={e => setMaxTime(Number(e.target.value))}
                className="w-full accent-amber-500"
              />
              <div className="flex justify-between text-xs text-stone-300 mt-1">
                <span>15m</span><span>1h</span><span>2h</span><span>3h</span><span>4h</span>
              </div>
            </FilterSection>

            {/* Moteurs Engelstein */}
            <FilterSection title="Style / Moteur" defaultOpen={false}>
              <div className="flex flex-wrap gap-1.5">
                {Object.keys(ENGINES).map(key => (
                  <Chip
                    key={key}
                    active={selectedFamilies.includes(key)}
                    onClick={() => toggle(key)}
                    icon={ENGINE_ICONS[key]}
                    label={familyLabel(key)}
                    colorClass={selectedFamilies.includes(key) ? familyColor(key) : null}
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
                    icon={SUPPORT_ICONS[key]}
                    label={familyLabel(key)}
                    colorClass={selectedFamilies.includes(key) ? 'bg-stone-700 text-white border-stone-700' : null}
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
                    icon={EXTRA_ICONS[key]}
                    label={familyLabel(key)}
                    colorClass={selectedFamilies.includes(key) ? (EXTRA_COLORS?.[key] || 'bg-stone-700 text-white border-stone-700') : null}
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
                      colorClass={selectedCategories.includes(item.label) ? 'bg-amber-500 text-white border-amber-500' : null}
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
                      colorClass={selectedCategories.includes(item.label) ? 'bg-blue-500 text-white border-blue-500' : null}
                    />
                  ))}
                </div>
              </FilterSection>
            )}

            {/* Bouton lancer */}
            <button
              type="submit"
              disabled={colLoading || disLoading}
              className="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-black py-3.5 rounded-xl text-base transition-all shadow-sm"
            >
              {(colLoading || disLoading) ? 'Recherche…' : '🎲 Trouver des jeux'}
            </button>
          </aside>

          {/* ── Bouton mobile toggle filtres ─────────────────────────── */}
          <div className="lg:hidden w-full">
            <button
              type="button"
              onClick={() => setFiltersOpen(v => !v)}
              className="w-full flex items-center justify-between px-4 py-3 bg-white rounded-xl border border-stone-200 shadow-sm text-sm font-semibold text-stone-700 mb-4"
            >
              <span>🎛️ Filtres {activeFiltersCount > 0 && `(${activeFiltersCount})`}</span>
              <span className="text-stone-400">{filtersOpen ? '▲' : '▼'}</span>
            </button>
            {filtersOpen && (
              <div className="mb-4">
                {/* Les filtres s'affichent au-dessus (sidebar réutilisée) */}
              </div>
            )}
          </div>

          {/* ── Zone résultats ────────────────────────────────────────── */}
          <div className="flex-1 min-w-0">
            <ResultsPanel
              collectionResults={collectionResults}
              collectionLoading={colLoading}
              collectionError={colError}
              discoverResults={discoverResults}
              discoverLoading={disLoading}
              discoverError={disError}
              searched={searched}
            />
          </div>

        </div>
      </form>
    </div>
  )
}
