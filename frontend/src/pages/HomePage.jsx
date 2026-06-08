import { useState, useEffect, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { getHomeRecommendations } from '../api'
import GameCard from '../components/GameCard'
import { familyLabel, familyColor, isEngine } from '../utils/engelstein'

/* ── Carrousel horizontal ────────────────────────────────────────────── */
function Carousel({ items }) {
  const ref = useRef(null)

  const scroll = (dir) => {
    ref.current?.scrollBy({ left: dir * 280, behavior: 'smooth' })
  }

  return (
    <div className="relative group">
      {/* Bouton gauche */}
      <button
        onClick={() => scroll(-1)}
        className="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-9 h-9 bg-white border border-stone-200 shadow-md rounded-full flex items-center justify-center text-stone-600 hover:bg-amber-50 hover:border-amber-300 transition-all opacity-0 group-hover:opacity-100 -translate-x-3"
      >
        ‹
      </button>

      {/* Piste */}
      <div
        ref={ref}
        className="flex gap-3 overflow-x-auto scrollbar-hide pb-2 snap-x snap-mandatory"
        style={{ scrollbarWidth: 'none' }}
      >
        {items.map(({ game, reason }) => (
          <div key={game.id} className="flex-shrink-0 w-44 sm:w-52 snap-start">
            <GameCard game={game} reason={reason} />
          </div>
        ))}
      </div>

      {/* Bouton droit */}
      <button
        onClick={() => scroll(1)}
        className="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-9 h-9 bg-white border border-stone-200 shadow-md rounded-full flex items-center justify-center text-stone-600 hover:bg-amber-50 hover:border-amber-300 transition-all opacity-0 group-hover:opacity-100 translate-x-3"
      >
        ›
      </button>
    </div>
  )
}

/* ── Onglets décennie ────────────────────────────────────────────────── */
function DecadeTabs({ byDecade }) {
  const labels = Object.keys(byDecade)
  const [active, setActive] = useState(labels[0])

  if (labels.length === 0) return null

  const games = byDecade[active] ?? []

  return (
    <div className="mt-10">
      <h2 className="text-xl font-black text-stone-900 mb-4">Par époque</h2>

      {/* Onglets */}
      <div className="flex gap-1 border-b border-stone-200 mb-5 overflow-x-auto">
        {labels.map(label => (
          <button
            key={label}
            onClick={() => setActive(label)}
            className={
              'flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px whitespace-nowrap ' +
              (active === label
                ? 'border-amber-500 text-amber-700'
                : 'border-transparent text-stone-500 hover:text-stone-700')
            }
          >
            {label}
            <span className={`text-xs px-1.5 py-0.5 rounded-full font-bold ${
              active === label ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-500'
            }`}>
              {byDecade[label].length}
            </span>
          </button>
        ))}
      </div>

      {/* Cartes */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
        {games.map(({ game, reason }) => (
          <GameCard key={game.id} game={game} reason={reason} />
        ))}
      </div>
    </div>
  )
}

/* ── Page ────────────────────────────────────────────────────────────── */
export default function HomePage() {
  const navigate                       = useNavigate()
  const { user, loading: authLoading } = useAuth()

  const [reco, setReco]             = useState(null)
  const [recoLoading, setRecoLoading] = useState(false)
  const [recoError, setRecoError]   = useState(null)

  useEffect(() => {
    if (authLoading || !user) return

    setRecoLoading(true)
    setRecoError(null)
    getHomeRecommendations()
      .then(data => setReco(data))
      .catch(err  => setRecoError(err.message))
      .finally(() => setRecoLoading(false))
  }, [user, authLoading])

  const hasRecent  = reco?.recent?.length > 0
  const hasDecades = reco?.byDecade && Object.keys(reco.byDecade).length > 0

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">

      {/* ── Hero ───────────────────────────────────────────────────── */}
      <div className="text-center mb-10">
        <div className="text-7xl mb-4">🎲</div>
        <h1 className="text-4xl sm:text-5xl font-black text-stone-900 mb-3 leading-tight">
          À quoi on joue ?
        </h1>
        <p className="text-stone-400 mb-7 max-w-sm mx-auto">
          Trouvez le jeu parfait pour ce soir en moins de 30 secondes.
        </p>
        <div className="flex flex-col sm:flex-row gap-3 justify-center">
          <button
            onClick={() => navigate('/recommander')}
            className="bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-lg font-bold px-8 py-4 rounded-2xl shadow-xl transition-all"
          >
            Ce soir on joue →
          </button>
          <button
            onClick={() => navigate('/rechercher')}
            className="bg-white hover:bg-stone-50 active:scale-95 text-stone-700 text-lg font-bold px-8 py-4 rounded-2xl shadow border border-stone-200 transition-all"
          >
            🔍 Chercher
          </button>
        </div>
      </div>

      {/* ── Non connecté ───────────────────────────────────────────── */}
      {!authLoading && !user && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm text-stone-500">
          {[
            { icon: '🔍', text: 'Chercher un jeu', to: '/rechercher' },
            { icon: '✨', text: 'Recommandations', to: '/recommander' },
            { icon: '📚', text: 'Ma ludothèque',   to: '/ludotheque' },
            { icon: '📊', text: 'Mes parties',     to: '/parties' },
          ].map(({ icon, text, to }) => (
            <button key={to} onClick={() => navigate(to)}
              className="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-white hover:shadow-sm transition-all cursor-pointer">
              <span className="text-3xl">{icon}</span>
              <span>{text}</span>
            </button>
          ))}
        </div>
      )}

      {/* ── Connecté ───────────────────────────────────────────────── */}
      {!authLoading && user && (
        <div>

          {/* Chargement */}
          {recoLoading && (
            <div className="flex items-center justify-center py-16 gap-3 text-stone-400">
              <div className="w-5 h-5 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
              <span className="text-sm">Analyse de votre profil…</span>
            </div>
          )}

          {/* Erreur */}
          {!recoLoading && recoError && (
            <div className="text-center py-10 text-stone-400">
              <p className="text-sm">Impossible de charger les recommandations.</p>
            </div>
          )}

          {/* ── Section 1 : Nouveautés ─────────────────────────────── */}
          {!recoLoading && hasRecent && (
            <div>
              {/* Familles détectées */}
              <div className="mb-5">
                <h2 className="text-xl font-black text-stone-900 mb-2">Nouveautés pour vous</h2>
                <div className="flex flex-wrap items-center gap-2 text-sm text-stone-500">
                  <span>D'après vos goûts :</span>
                  {reco.families.map(key => (
                    <span key={key} className={`text-xs px-2.5 py-1 rounded-full border font-medium ${
                      isEngine(key) ? familyColor(key) : 'bg-stone-100 text-stone-600 border-stone-200'
                    }`}>
                      {familyLabel(key)}
                    </span>
                  ))}
                </div>
              </div>

              <Carousel items={reco.recent} />

              <div className="mt-4 text-center">
                <Link to="/recommander" className="text-sm text-amber-600 hover:underline font-medium">
                  Voir plus de recommandations →
                </Link>
              </div>
            </div>
          )}

          {/* ── Section 2 : Par époque ────────────────────────────── */}
          {!recoLoading && hasDecades && (
            <DecadeTabs byDecade={reco.byDecade} />
          )}

          {/* Pas encore de données */}
          {!recoLoading && !recoError && reco !== null && !hasRecent && !hasDecades && (
            <div className="text-center py-10 text-stone-400">
              <div className="text-4xl mb-3">📭</div>
              <p className="font-medium text-stone-600 mb-1">Pas encore de recommandations</p>
              <p className="text-sm mb-5">
                Importez votre collection BGG et notez quelques jeux pour débloquer des suggestions personnalisées.
              </p>
              <Link to="/importer"
                className="inline-block bg-amber-500 hover:bg-amber-600 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-colors">
                Importer ma collection BGG
              </Link>
            </div>
          )}

        </div>
      )}
    </div>
  )
}
