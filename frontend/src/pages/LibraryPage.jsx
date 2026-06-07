import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { getLibrary } from '../api'
import { useAuth } from '../context/AuthContext'
import GameCard from '../components/GameCard'

/* ── Hook pour un onglet paginé ────────────────────────────────────────── */
function useLibraryTab(played, active) {
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
      const data = await getLibrary(pageNum, played)
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
  }, [played])

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

export default function LibraryPage() {
  const { user }          = useAuth()
  const [activeTab, setActiveTab] = useState('played')

  const playedTab   = useLibraryTab(true,  activeTab === 'played')
  const unplayedTab = useLibraryTab(false, activeTab === 'unplayed')

  const tab = activeTab === 'played' ? playedTab : unplayedTab
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
        {/* Total de l'onglet actif */}
        {tab.total !== null && (
          <span className="text-sm text-stone-400 tabular-nums">
            {tab.total} jeu{tab.total > 1 ? 'x' : ''}
          </span>
        )}
      </div>

      {/* Onglets */}
      <div className="flex gap-1 border-b border-stone-200 mb-5">
        {TABS.map(t => (
          <button
            key={t.key}
            onClick={() => setActiveTab(t.key)}
            className={
              'px-4 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-all ' +
              (activeTab === t.key
                ? 'border-amber-500 text-amber-700 bg-amber-50'
                : 'border-transparent text-stone-500 hover:text-stone-700 hover:bg-stone-50')
            }
          >
            {t.label}
            {/* Compteur lazy : affiché seulement une fois chargé */}
            {(activeTab === t.key ? playedTab : unplayedTab).total !== null && (
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

      {/* Erreur */}
      {tab.error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-5">
          {tab.error}
        </div>
      )}

      {/* Liste vide */}
      {!tab.error && tab.games.length === 0 && !tab.loading && (
        <div className="text-center py-14 text-stone-500">
          <div className="text-5xl mb-4">{tabDef.emptyIcon}</div>
          <p className="font-semibold text-stone-700 mb-1">{tabDef.emptyMsg}</p>
          {tabDef.emptyHint && <p className="text-sm text-stone-400 mb-6">{tabDef.emptyHint}</p>}
          {activeTab === 'played' && (
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
          {tab.total ?? tab.games.length} jeu{(tab.total ?? tab.games.length) > 1 ? 'x' : ''}
        </p>
      )}
    </div>
  )
}
