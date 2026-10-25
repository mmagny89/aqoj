import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { getLibrary, getUserPreferences, recomputeUserPreferences } from '../api'
import { useAuth } from '../context/AuthContext'
import GameCard from '../components/GameCard'
import { familyLabel, familyColor, SUPPORT_COLOR } from '../utils/engelstein'

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
  { key: 'played',   label: 'Déjà joués',        played: true,  emptyMsg: "Vous n'avez noté aucun jeu sur BGG.", emptyHint: 'Notez vos jeux sur BoardGameGeek et importez votre collection.' },
  { key: 'unplayed', label: 'Pas encore joués',   played: false, emptyMsg: 'Tous vos jeux ont été joués !',        emptyHint: null },
]

/* ── Bloc préférences mécaniques ─────────────────────────────────────── */
function PrefGroup({ title, children }) {
  return (
    <div>
      <h3 className="mb-2 text-sm font-bold uppercase text-stone-600">{title}</h3>
      <div className="flex flex-wrap gap-2">{children}</div>
    </div>
  )
}

function MechanicPrefsBlock({ prefs }) {
  if (!prefs) return null
  const engines   = prefs.topEngines   ?? []
  const support   = prefs.topSupport   ?? []
  const cats      = prefs.topCategories ?? []
  if (engines.length + support.length + cats.length === 0) return null

  return (
    <section aria-labelledby="titre-styles" className="panel flex flex-col gap-4 p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="titre-styles" className="text-2xl">Vos styles de jeu</h2>
        <span className="text-sm text-stone-600">Calculés depuis votre collection et vos parties</span>
      </div>

      {engines.length > 0 && (
        <PrefGroup title="Moteurs favoris">
          {engines.map((key, i) => (
            <span key={key} className={`rounded-full border px-3 py-1.5 text-sm font-bold ${familyColor(key)}`}>
              {i === 0 && <span aria-hidden="true">★ </span>}
              {familyLabel(key)}
            </span>
          ))}
        </PrefGroup>
      )}

      {support.length > 0 && (
        <PrefGroup title="Mécaniques de support">
          {support.map(key => (
            <span key={key} className={`rounded-full border px-3 py-1.5 text-sm font-bold ${SUPPORT_COLOR}`}>{familyLabel(key)}</span>
          ))}
        </PrefGroup>
      )}

      {cats.length > 0 && (
        <PrefGroup title="Thèmes favoris">
          {cats.map(cat => <span key={cat} className="badge bg-amber-100 !text-sm">{cat}</span>)}
        </PrefGroup>
      )}

      {prefs.updatedAt && (
        <p className="text-sm text-stone-600">Mis à jour le {new Date(prefs.updatedAt).toLocaleDateString('fr-FR')}</p>
      )}
    </section>
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
  const unit   = subTab === 'ext' ? 'extension' : 'jeu'

  if (!user) {
    return (
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
        <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
          <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
          <h1 className="text-4xl">Ma ludothèque</h1>
          <p className="text-stone-600">Connectez-vous pour retrouver votre collection.</p>
          <Link to="/connexion" className="btn btn-primary">Se connecter</Link>
        </section>
      </div>
    )
  }

  if (tab.initialLoading) {
    return (
      <p role="status" className="flex min-h-[60vh] items-center justify-center gap-3 text-stone-600">
        <span className="h-6 w-6 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
        Chargement de votre ludothèque…
      </p>
    )
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">

      <div className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h1 className="text-4xl leading-tight sm:text-5xl">Ma ludothèque</h1>
          {tab.bggUsername && (
            <p className="mt-1 text-lg text-stone-600">Collection BGG de <strong className="text-ink">{tab.bggUsername}</strong></p>
          )}
        </div>
        {tab.total !== null && (
          <p className="badge bg-cream !text-base tabular-nums" aria-live="polite">
            {tab.total} {unit}{tab.total > 1 ? 's' : ''}
          </p>
        )}
      </div>

      {prefsLoading ? (
        <p role="status" className="panel flex items-center gap-3 p-4 text-stone-600">
          <span className="h-4 w-4 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
          Calcul de vos préférences mécaniques…
        </p>
      ) : (
        <MechanicPrefsBlock prefs={userPrefs} />
      )}

      {/* Onglets principaux */}
      <div role="tablist" aria-label="Jeux joués ou non" className="flex flex-wrap gap-2">
        {TABS.map(t => {
          const total = (t.key === 'played' ? playedTab : unplayedTab).total
          return (
            <button
              key={t.key}
              type="button"
              role="tab"
              id={`ludo-${t.key}`}
              aria-selected={activeTab === t.key}
              aria-controls="ludo-panneau"
              onClick={() => handleTabChange(t.key)}
              className={`chip ${activeTab === t.key ? 'is-on' : ''}`}
            >
              {t.label}
              {total !== null && <span className="ml-2 text-sm opacity-80">{total}</span>}
            </button>
          )
        })}
      </div>

      <div id="ludo-panneau" role="tabpanel" aria-labelledby={`ludo-${activeTab}`} className="flex flex-col gap-5">
        {/* Jeux de base / extensions */}
        <div role="group" aria-label="Type de fiches" className="flex gap-2">
          {[
            { key: 'base', label: 'Jeux de base' },
            { key: 'ext',  label: 'Extensions' },
          ].map(s => (
            <button
              key={s.key}
              type="button"
              aria-pressed={subTab === s.key}
              onClick={() => setSubTab(s.key)}
              className="chip"
            >
              {s.label}
            </button>
          ))}
        </div>

        {tab.error && (
          <p role="alert" className="panel border-brand-red bg-amber-100 p-4 font-bold">{tab.error}</p>
        )}

        {!tab.error && tab.games.length === 0 && !tab.loading && (
          <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
            <h2 className="text-2xl">
              {subTab === 'ext' ? 'Aucune extension dans cette catégorie.' : tabDef.emptyMsg}
            </h2>
            {subTab === 'base' && tabDef.emptyHint && <p className="text-stone-600">{tabDef.emptyHint}</p>}
            {activeTab === 'played' && subTab === 'base' && (
              <Link to="/importer" className="btn btn-primary">Importer ma collection</Link>
            )}
          </section>
        )}

        {tab.games.length > 0 && (
          <ul className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 2xl:grid-cols-5">
            {tab.games.map(game => (
              <li key={game.id}>
                <GameCard game={game} userRating={tab.userRatings[game.id] ?? null} />
              </li>
            ))}
          </ul>
        )}

        <InfiniteScrollSentinel onVisible={tab.loadMore} />

        {tab.loading && tab.games.length > 0 && (
          <p role="status" className="py-4 text-center">
            <span className="inline-block h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
            <span className="sr-only">Chargement de la suite</span>
          </p>
        )}

        {!tab.hasMore && tab.games.length > 0 && (
          <p className="py-2 text-center text-sm text-stone-600">
            {tab.total ?? tab.games.length} {unit}{(tab.total ?? tab.games.length) > 1 ? 's' : ''}
          </p>
        )}
      </div>
    </div>
  )
}
