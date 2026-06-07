import { useState } from 'react'
import { Link } from 'react-router-dom'
import { getRecommendations } from '../api'
import GameCard from '../components/GameCard'
import { useAuth } from '../context/AuthContext'
import { ENGINES, SUPPORT_FAMILIES, EXTRA_FAMILIES, familyLabel, familyColor, EXTRA_COLORS } from '../utils/engelstein'
import { FILTER_THEMES, FILTER_TYPES, categoryInfo } from '../utils/categories'

const ENGINE_ICONS = {
  worker_placement: '🏗️',
  deck_building:    '🃏',
  engine_building:  '⚙️',
  area_control:     '🗺️',
  hand_management:  '✋',
  auction:          '🔨',
}

const SUPPORT_ICONS = {
  card_play:           '🂠',
  spatial_placement:   '🧩',
  movement:            '🚶',
  resource_management: '💰',
  resolution:          '🎲',
  uncertainty:         '🎭',
  cooperation:         '🤝',
  scoring:             '🏆',
}

const EXTRA_ICONS = {
  solo:       '🧍',
  real_time:  '⏰',
  dexterity:  '🤸',
  legacy:     '📖',
}

export default function RecommendationPage() {
  const { user } = useAuth()
  const [players, setPlayers]                   = useState(4)
  const [maxTime, setMaxTime]                   = useState(60)
  const [selectedFamilies, setSelectedFamilies]     = useState([])
  const [selectedCategories, setSelectedCategories] = useState([])
  const [collectionResults, setCollectionResults] = useState(null)
  const [discoverResults, setDiscoverResults]     = useState(null)
  const [loading, setLoading]   = useState(false)
  const [error, setError]       = useState(null)
  const [activeTab, setActiveTab] = useState('collection')
  const [searched, setSearched]   = useState(false)

  const toggle = (key) =>
    setSelectedFamilies(prev =>
      prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]
    )

  const toggleCat = (cat) =>
    setSelectedCategories(prev =>
      prev.includes(cat) ? prev.filter(c => c !== cat) : [...prev, cat]
    )

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setCollectionResults(null)
    setDiscoverResults(null)

    try {
      const params = { players, maxTime, families: selectedFamilies, categories: selectedCategories }
      const [col, disc] = await Promise.all([
        getRecommendations({ ...params, scope: 'collection' }),
        getRecommendations({ ...params, scope: 'discover' }),
      ])
      setCollectionResults(col)
      setDiscoverResults(disc)
      setSearched(true)
      // Onglet actif = celui qui a le plus de résultats, en priorité "collection"
      setActiveTab(col.length >= disc.length ? 'collection' : 'discover')
    } catch (err) {
      setError(err.message || 'Erreur. Avez-vous importé votre collection BGG ?')
    } finally {
      setLoading(false)
    }
  }

  if (!user) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-16 text-center text-stone-500">
        <div className="text-5xl mb-4">🔒</div>
        <p className="font-semibold text-lg text-stone-800 mb-2">Connexion requise</p>
        <p className="text-sm mb-6">Les recommandations sont personnalisées depuis votre collection BGG.</p>
        <Link to="/connexion" className="bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-3 rounded-xl">
          Se connecter
        </Link>
      </div>
    )
  }

  const visibleResults = activeTab === 'discover' ? discoverResults : collectionResults

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <h2 className="text-3xl font-black text-stone-900 mb-2">Ce soir on joue à…</h2>
      <p className="text-stone-400 text-sm mb-6">
        Recommandations personnalisées selon vos critères.
      </p>

      <form onSubmit={handleSubmit} className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-6">

        {/* Joueurs */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-3">Combien de joueurs ?</p>
          <div className="flex gap-2 flex-wrap">
            {[1, 2, 3, 4, 5, 6, 7, 8].map(n => (
              <button
                key={n} type="button" onClick={() => setPlayers(n)}
                className={'w-11 h-11 rounded-xl font-bold text-lg transition-all ' + (players === n ? 'bg-amber-500 text-white shadow' : 'bg-stone-100 text-stone-600 hover:bg-stone-200')}
              >{n}</button>
            ))}
          </div>
        </div>

        {/* Durée */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-3">
            Durée max :{' '}
            <span className="text-amber-600 font-bold">
              {maxTime < 60 ? `${maxTime} min` : `${Math.floor(maxTime / 60)}h${maxTime % 60 ? maxTime % 60 : ''}`}
            </span>
          </p>
          <input
            type="range" min={15} max={180} step={15} value={maxTime}
            onChange={e => setMaxTime(Number(e.target.value))}
            className="w-full accent-amber-500"
          />
          <div className="flex justify-between text-xs text-stone-400 mt-1">
            <span>15 min</span><span>1h</span><span>1h30</span><span>2h</span><span>3h</span>
          </div>
        </div>

        {/* Style de jeu */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-1">
            Style de jeu{' '}
            <span className="font-normal text-stone-400">(optionnel)</span>
          </p>
          {(selectedFamilies.length > 0 || selectedCategories.length > 0) && (
            <button type="button" onClick={() => { setSelectedFamilies([]); setSelectedCategories([]) }}
              className="text-xs text-stone-400 hover:text-stone-600 underline mb-3 block">
              Tout désélectionner
            </button>
          )}

          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-3">Moteurs principaux</p>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {Object.keys(ENGINES).map(key => {
              const active = selectedFamilies.includes(key)
              return (
                <button key={key} type="button" onClick={() => toggle(key)}
                  className={'flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium border transition-all ' +
                    (active ? familyColor(key) + ' ring-2 ring-offset-1 ring-current/20' : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-stone-100')}>
                  <span className="text-base flex-shrink-0">{ENGINE_ICONS[key]}</span>
                  <span className="leading-tight text-left">{familyLabel(key)}</span>
                </button>
              )
            })}
          </div>

          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-4">Mécaniques de support</p>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {Object.keys(SUPPORT_FAMILIES).map(key => {
              const active = selectedFamilies.includes(key)
              return (
                <button key={key} type="button" onClick={() => toggle(key)}
                  className={'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium border transition-all ' +
                    (active ? 'bg-stone-700 text-white border-stone-700' : 'bg-stone-50 text-stone-500 border-stone-200 hover:bg-stone-100')}>
                  <span className="flex-shrink-0">{SUPPORT_ICONS[key]}</span>
                  <span className="leading-tight text-left">{familyLabel(key)}</span>
                </button>
              )
            })}
          </div>

          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-4">Modes & caractéristiques</p>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {Object.keys(EXTRA_FAMILIES).map(key => {
              const active = selectedFamilies.includes(key)
              const activeColor = EXTRA_COLORS[key] ?? 'bg-stone-700 text-white border-stone-700'
              return (
                <button key={key} type="button" onClick={() => toggle(key)}
                  className={'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium border transition-all ' +
                    (active ? activeColor + ' ring-1 ring-offset-1 ring-current/20' : 'bg-stone-50 text-stone-500 border-stone-200 hover:bg-stone-100')}>
                  <span className="flex-shrink-0">{EXTRA_ICONS[key]}</span>
                  <span className="leading-tight text-left">{familyLabel(key)}</span>
                </button>
              )
            })}
          </div>
        </div>

        {/* ── Thèmes & univers ──────────────────────────────────────── */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-1">
            Thème / univers{' '}
            <span className="font-normal text-stone-400">(optionnel)</span>
          </p>

          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-3">🌍 Univers</p>
          <div className="flex flex-wrap gap-1.5">
            {FILTER_THEMES.map(cat => {
              const { label, emoji } = categoryInfo(cat)
              const active = selectedCategories.includes(cat)
              return (
                <button key={cat} type="button" onClick={() => toggleCat(cat)}
                  className={'flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ' +
                    (active
                      ? 'bg-amber-500 text-white border-amber-500'
                      : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-amber-50 hover:border-amber-300')}>
                  <span>{emoji}</span><span>{label}</span>
                </button>
              )
            })}
          </div>

          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-4">🎯 Type de jeu</p>
          <div className="flex flex-wrap gap-1.5">
            {FILTER_TYPES.map(cat => {
              const { label, emoji } = categoryInfo(cat)
              const active = selectedCategories.includes(cat)
              return (
                <button key={cat} type="button" onClick={() => toggleCat(cat)}
                  className={'flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ' +
                    (active
                      ? 'bg-blue-500 text-white border-blue-500'
                      : 'bg-stone-50 text-stone-600 border-stone-200 hover:bg-blue-50 hover:border-blue-300')}>
                  <span>{emoji}</span><span>{label}</span>
                </button>
              )
            })}
          </div>
        </div>

        <button type="submit" disabled={loading}
          className="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-4 rounded-xl text-lg transition-all">
          {loading ? '🎲 Recherche en cours…' : 'Trouver des jeux →'}
        </button>
      </form>

      {error && (
        <div className="mt-6 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">{error}</div>
      )}

      {/* ── Résultats avec onglets ──────────────────────────────────────── */}
      {searched && (
        <div className="mt-8">

          {/* Onglets */}
          <div className="flex gap-1 border-b border-stone-200 mb-5">
            {[
              { key: 'collection', icon: '📚', label: 'Ma collection', results: collectionResults },
              { key: 'discover',   icon: '🔭', label: 'À découvrir',   results: discoverResults },
            ].map(tab => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={
                  'flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors -mb-px ' +
                  (activeTab === tab.key
                    ? 'border-amber-500 text-amber-700'
                    : 'border-transparent text-stone-500 hover:text-stone-700')
                }
              >
                <span>{tab.icon}</span>
                <span>{tab.label}</span>
                {tab.results !== null && (
                  <span className={`ml-1 text-xs px-1.5 py-0.5 rounded-full font-bold ${
                    activeTab === tab.key ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-500'
                  }`}>
                    {tab.results.length}
                  </span>
                )}
              </button>
            ))}
          </div>

          {/* Contenu de l'onglet actif */}
          {visibleResults === null && (
            <div className="flex justify-center py-8">
              <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
            </div>
          )}

          {visibleResults !== null && visibleResults.length === 0 && (
            <div className="text-center text-stone-500 py-10">
              <div className="text-4xl mb-3">🤷</div>
              <p className="font-medium">Aucun jeu trouvé pour ces critères.</p>
              {activeTab === 'collection' && (
                <p className="text-sm mt-2">
                  Essayez d'élargir les critères ou{' '}
                  <Link to="/importer" className="text-amber-600 underline">importez votre collection BGG</Link>.
                </p>
              )}
            </div>
          )}

          {visibleResults !== null && visibleResults.length > 0 && (
            <>
              <p className="text-sm text-stone-400 mb-3">
                {visibleResults.length} jeu{visibleResults.length > 1 ? 'x' : ''}{' '}
                {activeTab === 'discover' ? 'à découvrir' : 'recommandé' + (visibleResults.length > 1 ? 's' : '')}
                {selectedFamilies.length > 0 && (
                  <span className="ml-1">· {selectedFamilies.map(f => familyLabel(f)).join(', ')}</span>
                )}
              </p>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {visibleResults.map(({ game, reason }, i) => (
                  <GameCard key={game.id} game={game} reason={reason} rank={i + 1} />
                ))}
              </div>
            </>
          )}

        </div>
      )}
    </div>
  )
}
