import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { getLibrary, getUserPreferences, recomputeUserPreferences } from '../api'
import { useAuth } from '../context/AuthContext'
import GameCard from '../components/GameCard'
import { familyLabel, familyColor, SUPPORT_COLOR, EXTRA_COLORS, EXTRA_FAMILIES } from '../utils/engelstein'

/* ── Hook pour un onglet paginé ────────────────────────────────────────── */
// isExpansion: true = extensions seulement, false = base seulement, null = tous
function useLibraryTab(played, active, isExpansion = null) {
  const [games, setGames]           = useState([])
  const [userRatings, setUserRatings] = useState({})
  const [bggUsername, setBggUsername] = useState(null)
  const [page, setPage]             = useState(1)
  const [hasMore, setHasMore]       = useState(true)
  const [total, setTotal]           = useState(null)
  const [loading, setLoading]       = useState(false)
  const [initialLoading, setInitialLoading] = useState(true)
  const [error, setError]           = useState(null)
  const loaded                      = useRef(false)

  const fetchPage = useCallback(async (pageNum, replace) => {
    setLoading(true)
    try {
      const data = await getLibrary(pageNum, played, isExpansion)
      setBggUsername(data.bggUsername)
      setTotal(data.total)
      setGames(prev => replace ? data.games : [...prev, ...data.games])
      setHasMore(data.hasMore)
      if (replace) setUserRatings(data.userRatings ?? {})
      else setUserRatings(prev => ({ ...prev, ...(data.userRatings ?? {}) }))
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
      if (replace) setInitialLoading(false)
    }
  }, [played, isExpansion])

  // Reset quand isExpansion change
  useEffect(() => {
    loaded.current = false
    setGames([])
    setPage(1)
    setTotal(null)
    setHasMore(true)
  }, [isExpansion])

  // Chargement initial : déclenché la première fois que l'onglet devient actif
  useEffect(() => {
    if (active && !loaded.current) {
      loaded.current = true
      setInitialLoading(true)
      fetchPage(1, true)
    }
  }, [active, fetchPage])

  // Pages suivantes
  useEffect(() => {
    if (page > 1) fetchPage(page, false)
  }, [page]) // eslint-disable-line react-hooks/exhaustive-deps

  const loadMore = useCallback(() => {
    if (hasMore && !loading) setPage(p => p + 1)
  }, [hasMore, loading])

  return { games, userRatings, bggUsername, hasMore, total, loading, initialLoading, error, loadMore }
}

/* ── Sentinelle d'infinite scroll ─────────────────────────────────────── */
function InfiniteScrollSentinel({ onVisible }) {
  const ref = useRef(null)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const obs = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting) onVisible() },
      { rootMargin: '200px' }
    )
    obs.observe(el)
    return () => obs.disconnect()
  }, [onVisible])
  return <div ref={ref} className="h-4" />
}

/* ── Composant principal ──────────────────────────────────────────────── */
const TABS = [
  { key: 'played',   label: '⭐ Déjà joués',        played: true,  emptyIcon: '🎮', emptyMsg: 'Vous n\'avez noté aucun jeu sur BGG.', emptyHint: 'Notez vos jeux sur BoardGameGeek et importez votre collection.' },
  { key: 'unplayed', label: '🎁 Pas encore joués',   played: false, emptyIcon: '🏆', emptyMsg: 'Tous vos jeux ont été joués !',          emptyHint: null },
]

/* ── Bloc préférences mécaniques ─────────────────────────────────────── */
function MechanicPrefsBlock({ prefs }) {
  if (!prefs) return null
  const engines   = prefs.topEngines   ?? []
  const support   = prefs.topSupport   ?? []
  const cats      = prefs.topCategories ?? []
  const allFams   = [...engines, ...support]
  if (allFams.length === 0 && cats.length === 0) return null

  return (
    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 mb-6 space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="font-bold text-stone-800 text-sm">Vos styles de jeu</h3>
        <span className="text-xs text-stone-400">calculé depuis votre collection et vos parties</span>
      </div>

      {engines.length > 0 && (
        <div>
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">⚙️ Moteurs favoris</p>
          <div className="flex flex-wrap gap-2">
            {engines.map((key, i) => (
              <div key={key} className={`flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold ${familyColor(key)}`}>
                {i === 0 && <span className="text-xs">⭐</span>}
                {familyLabel(key)}
              </div>
            ))}
          </div>
        </div>
      )}

      {support.length > 0 && (
        <div>
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">🔧 Mécaniques de support</p>
          <div className="flex flex-wrap gap-2">
            {support.map(key => (
              <span key={key} className={`px-3 py-1.5 rounded-full border text-xs font-semibold ${SUPPORT_COLOR}`}>
                {familyLabel(key)}
              </span>
            ))}
          </div>
        </div>
      )}

      {cats.length > 0 && (
        <div>
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">🎭 Thèmes / Catégories favoris</p>
          <div className="flex flex-wrap gap-2">
            {cats.map(cat => (
              <span key={cat} className="px-3 py-1.5 rounded-full border border-amber-200 bg-amber-50 text-amber-800 text-xs font-semibold">
                {cat}
              </span>
            ))}
          </div>
        </div>
      )}

      {prefs.updatedAt && (
        <p className="text-xs text-stone-300">
          Mis à jour le {new Date(prefs.updatedAt).toLocaleDateString('fr-FR')}
        </p>
      )}
    </div>
  )
}

export default function LibraryPage() {
  const { user }     = useAuth()
  const [activeTab, setActiveTab] = useState('played')
  const [subTab,    setSubTab]    = useState('base')  // 'base' | 'ext'
  const [userPrefs, setUserPrefs] = useState(null)
  const [prefsLoading, setPrefsLoading] = useState(false)

  // Charger les préférences, et recalculer si elles n'existent pas encore
  useEffect(() => {
    if (!user) return
    getUserPreferences().then(prefs => {
      if (prefs) {
        setUserPrefs(prefs)
      } else {
        // Pas encore calculées → on déclenche le calcul automatiquement
        setPrefsLoading(true)
        recomputeUserPreferences()
          .then(setUserPrefs)
          .catch(() => {})
          .finally(() => setPrefsLoading(false))
      }
    }).catch(() => {})
  }, [user])

  // Réinitialiser le sous-onglet quand on change d'onglet principal
  const handleTabChange = (key) => {
    setActiveTab(key)
    setSubTab('base')
  }

  const isExpansionFilter = subTab === 'ext' ? true : false

  const playedTab   = useLibraryTab(true,  activeTab === 'played',   isExpansionFilter)
  const unplayedTab = useLibraryTab(false, activeTab === 'unplayed', isExpansionFilter)

  const tab    = activeTab === 'played' ? playedTab : unplayedTab
  const tabDef = TABS.find(t => t.key === activeTab)

  if (!user) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-8 text-center">
        <div className="text-5xl mb-4">📚</div>
        <h2 className="text-2xl font-black text-stone-900 mb-2">Ma ludothèque</h2>
        <p className="text-stone-500 mb-6">Connecte-toi pour accéder à ta collection personnelle.</p>
        <Link to="/connexion" className="px-6 py-3 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600">
          Se connecter
        </Link>
      </div>
    )
  }

  if (tab.initialLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
      </div>
    )
  }

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">

      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h2 className="text-3xl font-black text-stone-900">Ma ludothèque</h2>
          {tab.bggUsername && (
            <p className="text-sm text-stone-400 mt-0.5">
              Collection BGG de <span className="font-medium text-stone-600">{tab.bggUsername}</span>
            </p>
          )}
        </div>
        {tab.total !== null && (
          <span className="text-sm text-stone-400 tabular-nums">
            {tab.total} {subTab === 'ext' ? 'extension' : 'jeu'}{tab.total > 1 ? 'x' : ''}
          </span>
        )}
      </div>

      {/* Préférences mécaniques */}
      {prefsLoading ? (
        <div className="bg-white rounded-2xl border border-stone-200 p-4 mb-6 flex items-center gap-2 text-stone-400 text-sm">
          <div className="w-4 h-4 border-2 border-stone-200 border-t-amber-400 rounded-full animate-spin flex-shrink-0" />
          Calcul de vos préférences mécaniques…
        </div>
      ) : (
        <MechanicPrefsBlock prefs={userPrefs} />
      )}

      {/* Onglets principaux */}
      <div className="flex gap-1 border-b border-stone-200 mb-0">
        {TABS.map(t => (
          <button
            key={t.key}
            onClick={() => handleTabChange(t.key)}
            className={
              'px-4 py-2.5 text-sm font-semibold border-b-2 transition-all ' +
              (activeTab === t.key
                ? 'border-amber-500 text-amber-700'
                : 'border-transparent text-stone-500 hover:text-stone-700')
            }
          >
            {t.label}
            {(t.key === 'played' ? playedTab : unplayedTab).total !== null && (
              <span className={
                'ml-2 text-xs px-1.5 py-0.5 rounded-full font-bold ' +
                (activeTab === t.key ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-400')
              }>
                {(t.key === 'played' ? playedTab : unplayedTab).total}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Sous-onglets base / extensions */}
      <div className="flex gap-2 py-3 mb-4 border-b border-stone-100">
        {[
          { key: 'base', label: '🎲 Jeux de base' },
          { key: 'ext',  label: '➕ Extensions' },
        ].map(s => (
          <button
            key={s.key}
            onClick={() => setSubTab(s.key)}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all ${
              subTab === s.key
                ? 'bg-amber-500 text-white border-amber-500'
                : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50'
            }`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Erreur */}
      {tab.error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-5">
          {tab.error}
        </div>
      )}

      {/* Liste vide */}
      {!tab.error && tab.games.length === 0 && !tab.loading && (
        <div className="text-center py-14 text-stone-500">
          <div className="text-5xl mb-4">
            {subTab === 'ext' ? '🧩' : tabDef.emptyIcon}
          </div>
          <p className="font-semibold text-stone-700 mb-1">
            {subTab === 'ext'
              ? 'Aucune extension dans cette catégorie.'
              : tabDef.emptyMsg}
          </p>
          {subTab === 'base' && tabDef.emptyHint && (
            <p className="text-sm text-stone-400 mb-6">{tabDef.emptyHint}</p>
          )}
          {activeTab === 'played' && subTab === 'base' && (
            <Link to="/importer" className="inline-block px-5 py-2.5 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600 text-sm">
              Importer depuis BGG →
            </Link>
          )}
        </div>
      )}

      {/* Cartes */}
      {tab.games.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          {tab.games.map(game => (
            <GameCard
              key={game.id}
              game={game}
              userRating={tab.userRatings[game.id] ?? null}
            />
          ))}
        </div>
      )}

      {/* Infinite scroll */}
      <InfiniteScrollSentinel onVisible={tab.loadMore} />

      {/* Spinner de chargement des pages suivantes */}
      {tab.loading && tab.games.length > 0 && (
        <div className="text-center py-6">
          <div className="inline-block w-5 h-5 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
        </div>
      )}

      {/* Fin de liste */}
      {!tab.hasMore && tab.games.length > 0 && (
        <p className="text-center text-xs text-stone-400 py-4">
          {tab.total ?? tab.games.length} {subTab === 'ext' ? 'extension' : 'jeu'}{(tab.total ?? tab.games.length) > 1 ? 's' : ''}
        </p>
      )}
    </div>
  )
}
