import { useState, useEffect, useRef } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { getGame, createSession, getSimilarGames } from '../api'
import GameCard from '../components/GameCard'
import { familyLabel, familyColor, isEngine, isExtra, SUPPORT_COLOR } from '../utils/engelstein'
import { groupCategories, categoryInfo } from '../utils/categories'

/* ── Complexité visuelle ─────────────────────────────────────────────── */
function ComplexityBar({ value }) {
  const steps = [1, 2, 3, 4, 5]
  const label = value < 1.8 ? 'Accessible' : value < 2.5 ? 'Familial' : value < 3.5 ? 'Intermédiaire' : value < 4.2 ? 'Expert' : 'Très complexe'
  return (
    <div>
      <div className="flex gap-1 mb-1">
        {steps.map(s => (
          <div key={s} className={`h-2 flex-1 rounded-full transition-all ${s <= Math.round(value) ? 'bg-amber-400' : 'bg-stone-200'}`} />
        ))}
      </div>
      <div className="flex justify-between text-xs text-stone-400">
        <span>{value.toFixed(1)}/5</span>
        <span className="font-medium text-stone-600">{label}</span>
      </div>
    </div>
  )
}

/* ── Bloc mécanique ──────────────────────────────────────────────────── */
function MechanicBlock({ familyKey, bggMechanics, primary }) {
  const colorClass = primary ? familyColor(familyKey) : SUPPORT_COLOR
  const dotColor = familyColor(familyKey).includes('amber') ? 'bg-amber-500' : familyColor(familyKey).includes('pink') ? 'bg-pink-500' : familyColor(familyKey).includes('teal') ? 'bg-teal-500' : familyColor(familyKey).includes('red') ? 'bg-red-500' : familyColor(familyKey).includes('violet') ? 'bg-violet-500' : 'bg-lime-500'
  return (
    <div className={`rounded-xl border p-3 ${primary ? colorClass : 'border-stone-200 bg-stone-50'}`}>
      <div className="flex items-center gap-2 mb-2">
        {primary && <span className={`w-2 h-2 rounded-full flex-shrink-0 ${dotColor}`} />}
        <span className={`text-sm font-semibold ${primary ? '' : 'text-stone-600'}`}>
          {familyLabel(familyKey)}
        </span>
      </div>
      {bggMechanics?.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {bggMechanics.map(m => (
            <span key={m} className="text-xs bg-white/60 border border-current/10 px-2 py-0.5 rounded-full text-current/70">{m}</span>
          ))}
        </div>
      )}
    </div>
  )
}

/* ── Ligne jeu/extension cliquable (réutilisée dans plusieurs sections) ─ */
function GameRow({ g, currentId }) {
  const isCurrent = g.id === currentId
  return (
    <Link
      to={`/games/${g.id}`}
      className={`flex items-center gap-3 p-2.5 rounded-xl border transition-all hover:shadow-sm ${
        isCurrent
          ? 'bg-amber-50 border-amber-200 ring-1 ring-amber-300'
          : g.owned
            ? 'bg-emerald-50 border-emerald-200 hover:border-emerald-300'
            : 'bg-stone-50 border-stone-200 hover:border-stone-300'
      }`}
    >
      {g.imageUrl
        ? <img src={g.imageUrl} alt={g.name} className="w-10 h-10 rounded-lg object-cover flex-shrink-0" />
        : <div className="w-10 h-10 rounded-lg bg-stone-200 flex items-center justify-center flex-shrink-0 text-stone-400 text-lg">🎲</div>
      }
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-sm font-semibold text-stone-800 truncate">{g.name}</span>
          {isCurrent && <span className="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Cette extension</span>}
          {g.owned && !isCurrent && <span className="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">✓ possédée</span>}
          {g.isExpansion && !isCurrent && <span className="text-[10px] bg-stone-100 text-stone-500 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Extension</span>}
        </div>
        {g.yearPublished && <span className="text-xs text-stone-400">{g.yearPublished}</span>}
      </div>
      <div className="flex items-center gap-1.5 flex-shrink-0">
        {g.ratingBgg && <span className="text-xs font-bold text-amber-600">{g.ratingBgg.toFixed(1)}</span>}
        {!isCurrent && <span className="text-stone-300 text-xs">›</span>}
      </div>
    </Link>
  )
}

/* ── Boutons d'achat ─────────────────────────────────────────────────── */
const SHOPS = [
  {
    key: 'crique',
    label: 'La Crique',
    emoji: '🏪',
    url: (name) => `https://www.crique-aux-jeux.fr/recherche?controller=search&s=${encodeURIComponent(name)}`,
    color: 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100',
  },
  {
    key: 'ludipassion',
    label: 'Ludipassion',
    emoji: '🎮',
    url: (name) => `https://ludipassion.fr/search?q=${encodeURIComponent(name)}`,
    color: 'bg-violet-50 text-violet-700 border-violet-200 hover:bg-violet-100',
  },
  {
    key: 'passtemps',
    label: 'Le Passe Temps',
    emoji: '⏳',
    url: (name) => `https://www.le-passe-temps.com/recherche?controller=search&s=${encodeURIComponent(name)}`,
    color: 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100',
  },
  {
    key: 'philibert',
    label: 'Philibert',
    emoji: '📦',
    url: (name) => `https://www.philibertnet.com/fr/recherche?search_query=${encodeURIComponent(name)}`,
    color: 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100',
  },
]

function ShopButtons({ gameName }) {
  const searchName = gameName
  return (
    <div>
      <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">🛒 Acheter ce jeu</p>
      <div className="flex flex-wrap gap-2">
        {SHOPS.map(shop => (
          <a
            key={shop.key}
            href={shop.url(searchName)}
            target="_blank"
            rel="noopener noreferrer"
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors ${shop.color}`}
          >
            <span>{shop.emoji}</span>
            <span>{shop.label}</span>
          </a>
        ))}
        <a
          href={`https://boardgamegeek.com/boardgame/${''}`}
          className="hidden"
        />
      </div>
    </div>
  )
}

/* ── Page principale ─────────────────────────────────────────────────── */
export default function GameDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [game, setGame] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [descExpanded,  setDescExpanded]  = useState(false)
  const [similarGames,  setSimilarGames]  = useState(null)
  const [similarLimit,  setSimilarLimit]  = useState(5)
  const [similarLoading, setSimilarLoading] = useState(false)
  const [similarTotal,  setSimilarTotal]  = useState(null)

  const [showSessionForm, setShowSessionForm] = useState(false)
  const [sessionForm, setSessionForm] = useState({ playersCount: 4, rating: null, note: '' })
  const [submitting, setSubmitting] = useState(false)
  const [sessionDone, setSessionDone] = useState(false)
  const sessionRef = useRef(null)

  useEffect(() => {
    getGame(id)
      .then(setGame)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
    // Chargement initial : 5 jeux similaires
    setSimilarLoading(true)
    getSimilarGames(id, 5)
      .then(data => { setSimilarGames(data); setSimilarLimit(5) })
      .catch(() => {})
      .finally(() => setSimilarLoading(false))
  }, [id])

  const loadMoreSimilar = () => {
    const newLimit = similarLimit + 5
    setSimilarLoading(true)
    getSimilarGames(id, newLimit)
      .then(data => { setSimilarGames(data); setSimilarLimit(newLimit) })
      .catch(() => {})
      .finally(() => setSimilarLoading(false))
  }

  const openSessionForm = () => {
    setShowSessionForm(true)
    setSessionDone(false)
    setTimeout(() => sessionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50)
  }

  const handleSession = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await createSession({
        gameId: Number(id),
        playersCount: sessionForm.playersCount,
        rating: sessionForm.rating,
        note: sessionForm.note || null,
      })
      setSessionDone(true)
      setShowSessionForm(false)
    } catch (err) {
      alert(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="w-8 h-8 border-2 border-stone-200 border-t-amber-500 rounded-full animate-spin" />
    </div>
  )

  if (error || !game) return (
    <div className="max-w-6xl mx-auto px-4 py-16 text-center text-stone-500">
      <div className="text-5xl mb-4">😕</div>
      <p className="font-medium">{error || 'Jeu introuvable'}</p>
      <button onClick={() => navigate(-1)} className="mt-4 text-amber-600 hover:underline text-sm">← Retour</button>
    </div>
  )

  const playerRange = game.minPlayers === game.maxPlayers
    ? `${game.minPlayers} joueur${game.minPlayers > 1 ? 's' : ''}`
    : `${game.minPlayers}–${game.maxPlayers} joueurs`

  const primaryFamilies = game.primaryFamilies ?? []
  const primaryKeys     = primaryFamilies.map(p => p.family)
  const extraKeys       = (game.mechanicFamilies ?? []).filter(k => isExtra(k))
  const supportKeys     = (game.mechanicFamilies ?? [])
    .filter(k => !isEngine(k) && !isExtra(k) && !primaryKeys.includes(k))
  const byFamily        = game.mechanicsByFamily ?? {}
  const hasEngines      = primaryFamilies.some(p => p.isEngine)
  const mappedMechanics = Object.values(byFamily).flat()
  const unmappedBgg = (game.mechanics ?? []).filter(m => !mappedMechanics.includes(m))

  // game.name = nom d'affichage FR si dispo, sinon EN original
  // game.nameOriginal = nom BGG anglais original
  const displayName = game.name
  const hasFrName   = game.nameOriginal && game.nameOriginal !== game.name

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">

      {/* Nav */}
      <button onClick={() => navigate(-1)} className="text-sm text-stone-400 hover:text-stone-600 flex items-center gap-1">
        ← Retour
      </button>

      {/* ── Hero : image gauche + infos droite ────────────────────────── */}
      <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div className="flex flex-col sm:flex-row gap-0">

          {/* Image */}
          <div className="sm:w-64 lg:w-80 flex-shrink-0 bg-stone-100">
            {game.imageUrl ? (
              <img src={game.imageUrl} alt={game.name} className="w-full h-64 sm:h-full object-cover" />
            ) : (
              <div className="w-full h-64 sm:h-full flex items-center justify-center text-6xl text-stone-300">🎲</div>
            )}
          </div>

          {/* Infos */}
          <div className="flex-1 p-5 flex flex-col gap-4">

            {/* Titre + badges langue */}
            <div>
              <div className="flex items-start gap-2 flex-wrap mb-1">
                {hasFrName && (
                  <span className="text-xs bg-blue-50 text-blue-600 border border-blue-200 px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                    🇫🇷 Titre FR
                  </span>
                )}
                {game.isExpansion && (
                  <span className="text-xs bg-stone-100 text-stone-500 border border-stone-200 px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                    Extension
                  </span>
                )}
              </div>
              <h1 className="text-2xl font-black text-stone-900 leading-tight">{displayName}</h1>
              {hasFrName && (
                <p className="text-sm text-stone-400 mt-0.5">{game.nameOriginal}</p>
              )}
              {game.yearPublished && (
                <p className="text-stone-400 text-sm mt-0.5">{game.yearPublished}</p>
              )}
            </div>

            {/* Notes */}
            <div className="flex gap-3 flex-wrap">
              {game.bggUserRating != null && (
                <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                  <div className="text-2xl font-black text-emerald-600">{game.bggUserRating}</div>
                  <div className="text-xs text-emerald-500">ma note /10</div>
                </div>
              )}
              {game.ratingBgg != null && (
                <div className="bg-amber-50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                  <div className="text-2xl font-black text-amber-600">{game.ratingBgg.toFixed(1)}</div>
                  <div className="text-xs text-stone-400">note BGG /10</div>
                  {game.usersRated != null && (
                    <div className="text-xs text-stone-300">{game.usersRated.toLocaleString('fr')} votes</div>
                  )}
                </div>
              )}
            </div>

            {/* Stats en 4 colonnes */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div className="bg-stone-50 rounded-xl px-3 py-2.5 text-center">
                <div className="text-lg">👥</div>
                <div className="text-xs text-stone-400 leading-none mt-0.5">Joueurs</div>
                <div className="text-sm font-bold text-stone-800 mt-0.5">{playerRange}</div>
              </div>
              {game.playingTime > 0 && (
                <div className="bg-stone-50 rounded-xl px-3 py-2.5 text-center">
                  <div className="text-lg">⏱</div>
                  <div className="text-xs text-stone-400 leading-none mt-0.5">Durée</div>
                  <div className="text-sm font-bold text-stone-800 mt-0.5">
                    {game.playingTime < 60
                      ? `${game.playingTime} min`
                      : `${Math.floor(game.playingTime / 60)}h${game.playingTime % 60 ? game.playingTime % 60 + 'm' : ''}`
                    }
                  </div>
                </div>
              )}
              {game.minAge != null && game.minAge > 0 && (
                <div className="bg-stone-50 rounded-xl px-3 py-2.5 text-center">
                  <div className="text-lg">🎂</div>
                  <div className="text-xs text-stone-400 leading-none mt-0.5">Âge min.</div>
                  <div className="text-sm font-bold text-stone-800 mt-0.5">{game.minAge}+</div>
                </div>
              )}
              {game.complexity > 0 && (
                <div className="bg-stone-50 rounded-xl px-3 py-2.5 col-span-2 sm:col-span-1">
                  <div className="text-lg text-center">🧠</div>
                  <div className="text-xs text-stone-400 leading-none text-center mt-0.5">Complexité</div>
                  <div className="mt-1.5">
                    <ComplexityBar value={game.complexity} />
                  </div>
                </div>
              )}
            </div>

            {/* Bouton Enregistrer une partie */}
            <div className="mt-auto pt-2 border-t border-stone-100">
              {sessionDone ? (
                <div className="flex items-center gap-2 text-emerald-600 text-sm font-semibold">
                  <span>✅</span>
                  <span>Partie enregistrée !</span>
                  <button onClick={openSessionForm} className="ml-auto text-xs text-stone-400 hover:text-amber-600 hover:underline">
                    + Une autre
                  </button>
                </div>
              ) : (
                <button
                  onClick={openSessionForm}
                  className="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 active:scale-[.98] text-white font-bold py-3 rounded-xl transition-all shadow-sm"
                >
                  <span className="text-lg">📊</span>
                  <span>Enregistrer une partie</span>
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* ── Description ──────────────────────────────────────────────────── */}
      {game.description && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5">
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Description</p>
          <div className="relative">
            <p className={`text-stone-600 text-sm leading-relaxed ${descExpanded ? '' : 'line-clamp-4'}`}>
              {game.description}
            </p>
            {!descExpanded && game.description.length > 300 && (
              <div className="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-white to-transparent" />
            )}
          </div>
          {game.description.length > 300 && (
            <button onClick={() => setDescExpanded(v => !v)} className="mt-2 text-xs text-amber-600 hover:underline font-medium">
              {descExpanded ? 'Voir moins ↑' : 'Lire la suite ↓'}
            </button>
          )}
        </div>
      )}

      {/* ── Boutiques + lien BGG ─────────────────────────────────────────── */}
      <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 space-y-4">
        <ShopButtons gameName={game.name} />
        <div className="pt-3 border-t border-stone-100">
          <a
            href={`https://boardgamegeek.com/${game.bggType === 'boardgameexpansion' ? 'boardgameexpansion' : 'boardgame'}/${game.bggId}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2 text-sm text-stone-500 hover:text-stone-700 hover:underline"
          >
            <img src="https://cf.geekdo-images.com/static/img/favicon2.ico" alt="" className="w-4 h-4 rounded" />
            Voir la fiche sur BoardGameGeek
            {game.bggRank && <span className="text-xs text-stone-400">· Rang #{game.bggRank.toLocaleString('fr')}</span>}
          </a>
        </div>
      </div>

      {/* ── Mécaniques Engelstein ─────────────────────────────────────────── */}
      {(primaryFamilies.length > 0 || supportKeys.length > 0) && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 space-y-4">
          {primaryFamilies.length > 0 && (
            <div>
              <div className="flex items-center gap-2 mb-3">
                <p className="text-xs font-semibold text-stone-500 uppercase tracking-wide">
                  {hasEngines ? `Moteur${primaryFamilies.length > 1 ? 's' : ''} principal${primaryFamilies.length > 1 ? 'aux' : ''}` : 'Style principal'}
                </p>
                {game.primaryEngine && <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">validé</span>}
                <span className="text-xs text-stone-300 ml-auto">{hasEngines ? 'moteur Engelstein' : 'familles dominantes'}</span>
              </div>
              <div className="grid grid-cols-1 gap-2">
                {primaryFamilies.map(({ family, isEngine: eng }) => (
                  <MechanicBlock key={family} familyKey={family} bggMechanics={byFamily[family]} primary={eng} />
                ))}
              </div>
              {!hasEngines && (
                <p className="text-xs text-stone-400 mt-2 italic">Aucun moteur Engelstein identifié — familles les plus représentées affichées.</p>
              )}
            </div>
          )}
          {supportKeys.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Mécaniques de support</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {supportKeys.map(key => (
                  <MechanicBlock key={key} familyKey={key} bggMechanics={byFamily[key]} primary={false} />
                ))}
              </div>
            </div>
          )}
          {extraKeys.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Caractéristiques</p>
              <div className="flex flex-wrap gap-2">
                {extraKeys.map(key => (
                  <span key={key} className={`text-xs font-medium px-3 py-1.5 rounded-full border ${familyColor(key)}`}>{familyLabel(key)}</span>
                ))}
              </div>
            </div>
          )}
          {unmappedBgg.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-300 uppercase tracking-wide mb-2">Autres mécaniques BGG</p>
              <div className="flex flex-wrap gap-1.5">
                {unmappedBgg.map(m => (
                  <span key={m} className="text-xs bg-stone-100 text-stone-400 px-2.5 py-1 rounded-full">{m}</span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── Thèmes, types, catégories BGG ───────────────────────────────── */}
      {(game.categories?.length > 0 || game.mechanics?.length > 0) && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 space-y-4">
          {game.categories?.length > 0 && (() => {
            const { themes, types, other } = groupCategories(game.categories)
            return (
              <div className="space-y-3">
                {themes.length > 0 && (
                  <div>
                    <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">🌍 Thèmes</p>
                    <div className="flex flex-wrap gap-1.5">
                      {themes.map(({ name, label, emoji }) => (
                        <span key={name} className="text-xs bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                          <span>{emoji}</span><span>{label}</span>
                        </span>
                      ))}
                    </div>
                  </div>
                )}
                {types.length > 0 && (
                  <div>
                    <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">🎯 Type de jeu</p>
                    <div className="flex flex-wrap gap-1.5">
                      {types.map(({ name, label, emoji }) => (
                        <span key={name} className="text-xs bg-blue-50 text-blue-800 border border-blue-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                          <span>{emoji}</span><span>{label}</span>
                        </span>
                      ))}
                    </div>
                  </div>
                )}
                {other.length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {other.map(({ name }) => (
                      <span key={name} className="text-xs bg-stone-50 text-stone-500 border border-stone-200 px-2.5 py-1 rounded-full">{name}</span>
                    ))}
                  </div>
                )}
              </div>
            )
          })()}
          {game.mechanics?.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Mécaniques BGG (brut)</p>
              <div className="flex flex-wrap gap-1.5">
                {game.mechanics.map(m => (
                  <span key={m} className="text-xs bg-stone-100 text-stone-600 border border-stone-200 px-2.5 py-1 rounded-full">{m}</span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── Jeu de base (quand on consulte une extension) ─────────────────── */}
      {game.isExpansion && game.baseGames?.length > 0 && (
        <div className="bg-amber-50 rounded-2xl border border-amber-200 shadow-sm p-5">
          <p className="text-xs font-semibold text-amber-600 uppercase tracking-wide mb-3">🎲 Jeu de base</p>
          <div className="space-y-2 mb-4">
            {game.baseGames.map(bg => (
              <GameRow key={bg.id} g={bg} currentId={null} />
            ))}
          </div>

          {/* Extensions sœurs */}
          {game.siblingExpansions?.length > 0 && (
            <>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2 mt-4">
                ➕ Autres extensions ({game.siblingExpansions.length})
              </p>
              <div className="space-y-2">
                {game.siblingExpansions.map(sib => (
                  <GameRow key={sib.id} g={sib} currentId={game.id} />
                ))}
              </div>
            </>
          )}
        </div>
      )}

      {/* ── Extensions d'un jeu de base ──────────────────────────────────── */}
      {!game.isExpansion && game.expansions?.length > 0 && (() => {
        const visibleExps = game.expansions.filter(e => e.owned || (e.usersRated ?? 0) >= 50)
        if (visibleExps.length === 0) return null
        return (
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5">
            <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">
              ➕ Extensions ({visibleExps.length}{game.expansions.length > visibleExps.length ? ` sur ${game.expansions.length}` : ''})
            </p>
            <div className="space-y-2">
              {visibleExps.map(exp => (
                <GameRow key={exp.id} g={exp} currentId={null} />
              ))}
            </div>
          </div>
        )
      })()}

      {/* ── Jeux similaires ──────────────────────────────────────────────── */}
      {/* L'API /similar retourne des objets plats : { ...game, similarity, reason } */}
      {(similarGames?.length > 0 || similarLoading) && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5">
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-4">🎯 Jeux similaires</p>

          {similarLoading && similarGames === null ? (
            <div className="flex items-center gap-2 text-stone-400 text-sm py-4">
              <div className="w-4 h-4 border-2 border-stone-200 border-t-amber-400 rounded-full animate-spin" />
              Recherche…
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                {similarGames?.map((sg) => (
                  <GameCard
                    key={sg.id}
                    game={sg}
                    reason={sg.reason}
                    similarity={sg.similarity}
                  />
                ))}
              </div>

              {/* Bouton "Voir plus" */}
              <div className="mt-4 text-center">
                {similarLoading ? (
                  <div className="inline-flex items-center gap-2 text-stone-400 text-sm">
                    <div className="w-4 h-4 border-2 border-stone-200 border-t-amber-400 rounded-full animate-spin" />
                    Chargement…
                  </div>
                ) : similarGames?.length === similarLimit ? (
                  <button
                    onClick={loadMoreSimilar}
                    className="px-5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 text-sm font-semibold rounded-xl transition-all"
                  >
                    Voir 5 de plus ↓
                  </button>
                ) : (
                  <p className="text-xs text-stone-300">Tous les jeux similaires sont affichés</p>
                )}
              </div>
            </>
          )}
        </div>
      )}

      {/* ── Enregistrer une partie ────────────────────────────────────────── */}
      <div ref={sessionRef} className="bg-amber-50 rounded-2xl border border-amber-200 p-5">
        {sessionDone ? (
          <div className="text-center">
            <div className="text-3xl mb-2">✅</div>
            <p className="font-bold text-stone-800">Partie enregistrée !</p>
            <button onClick={() => setSessionDone(false)} className="mt-3 text-sm text-amber-600 hover:underline">
              Enregistrer une autre partie
            </button>
          </div>
        ) : !showSessionForm ? (
          <div className="flex items-center justify-between">
            <div>
              <p className="font-bold text-stone-800">Vous avez joué à ce jeu ?</p>
              <p className="text-sm text-stone-500">Notez votre expérience</p>
            </div>
            <button onClick={() => setShowSessionForm(true)} className="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2.5 rounded-xl transition-all">
              + Partie
            </button>
          </div>
        ) : (
          <form onSubmit={handleSession} className="space-y-4">
            <p className="font-bold text-stone-800">Enregistrer une partie</p>
            <div>
              <p className="text-sm font-semibold text-stone-600 mb-2">Joueurs</p>
              <div className="flex gap-2 flex-wrap">
                {[1, 2, 3, 4, 5, 6, 7, 8].map(n => (
                  <button key={n} type="button" onClick={() => setSessionForm(f => ({ ...f, playersCount: n }))}
                    className={'w-10 h-10 rounded-lg font-bold transition-all ' + (sessionForm.playersCount === n ? 'bg-amber-500 text-white' : 'bg-white text-stone-600 hover:bg-stone-100')}
                  >{n}</button>
                ))}
              </div>
            </div>
            <div>
              <p className="text-sm font-semibold text-stone-600 mb-2">Votre avis</p>
              <div className="flex gap-3">
                {[{ val: 5, icon: '👍', label: 'Aimé' }, { val: 1, icon: '👎', label: 'Pas aimé' }].map(({ val, icon, label }) => (
                  <button key={val} type="button" onClick={() => setSessionForm(f => ({ ...f, rating: f.rating === val ? null : val }))}
                    className={'flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-sm transition-all ' + (sessionForm.rating === val ? 'bg-amber-500 text-white' : 'bg-white text-stone-600 hover:bg-stone-100')}
                  >{icon} {label}</button>
                ))}
              </div>
            </div>
            <textarea
              value={sessionForm.note}
              onChange={e => setSessionForm(f => ({ ...f, note: e.target.value }))}
              placeholder="Un commentaire ? (optionnel)"
              rows={2}
              className="w-full px-3 py-2 rounded-xl border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none bg-white"
            />
            <div className="flex gap-3">
              <button type="submit" disabled={submitting} className="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-2.5 rounded-xl">
                {submitting ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button type="button" onClick={() => setShowSessionForm(false)} className="px-4 py-2.5 bg-white text-stone-600 rounded-xl font-medium hover:bg-stone-100">
                Annuler
              </button>
            </div>
          </form>
        )}
      </div>

    </div>
  )
}
