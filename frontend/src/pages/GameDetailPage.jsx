import { useState, useEffect } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { getGame, createSession } from '../api'
import { familyLabel, familyColor, isEngine, isExtra, SUPPORT_COLOR, ENGINES, SUPPORT_FAMILIES, EXTRA_FAMILIES } from '../utils/engelstein'
import { groupCategories, categoryInfo } from '../utils/categories'

/* ── Complexité visuelle ─────────────────────────────────────────────── */
function ComplexityBar({ value }) {
  const steps = [1, 2, 3, 4, 5]
  const label = value < 1.8 ? 'Accessible' : value < 2.5 ? 'Familial' : value < 3.5 ? 'Intermédiaire' : value < 4.2 ? 'Expert' : 'Très complexe'
  return (
    <div>
      <div className="flex gap-1 mb-1">
        {steps.map(s => (
          <div
            key={s}
            className={`h-2 flex-1 rounded-full transition-all ${
              s <= Math.round(value) ? 'bg-amber-400' : 'bg-stone-200'
            }`}
          />
        ))}
      </div>
      <div className="flex justify-between text-xs text-stone-400">
        <span>{value.toFixed(1)}/5</span>
        <span className="font-medium text-stone-600">{label}</span>
      </div>
    </div>
  )
}

/* ── Bloc mécanique principale ou de support ─────────────────────────── */
function MechanicBlock({ familyKey, bggMechanics, primary }) {
  const colorClass = primary ? familyColor(familyKey) : SUPPORT_COLOR
  return (
    <div className={`rounded-xl border p-3 ${primary ? colorClass : 'border-stone-200 bg-stone-50'}`}>
      <div className="flex items-center gap-2 mb-2">
        {primary && <span className={`w-2 h-2 rounded-full flex-shrink-0 ${familyColor(familyKey).includes('amber') ? 'bg-amber-500' : familyColor(familyKey).includes('pink') ? 'bg-pink-500' : familyColor(familyKey).includes('teal') ? 'bg-teal-500' : familyColor(familyKey).includes('red') ? 'bg-red-500' : familyColor(familyKey).includes('violet') ? 'bg-violet-500' : 'bg-lime-500'}`} />}
        <span className={`text-sm font-semibold ${primary ? '' : 'text-stone-600'}`}>
          {familyLabel(familyKey)}
        </span>
      </div>
      {bggMechanics?.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {bggMechanics.map(m => (
            <span key={m} className="text-xs bg-white/60 border border-current/10 px-2 py-0.5 rounded-full text-current/70">
              {m}
            </span>
          ))}
        </div>
      )}
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
  const [descExpanded, setDescExpanded] = useState(false)

  const [showSessionForm, setShowSessionForm] = useState(false)
  const [sessionForm, setSessionForm] = useState({ playersCount: 4, rating: null, note: '' })
  const [submitting, setSubmitting] = useState(false)
  const [sessionDone, setSessionDone] = useState(false)

  useEffect(() => {
    getGame(id)
      .then(setGame)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [id])

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

  const primaryFamilies = game.primaryFamilies ?? []          // [{family, isEngine}]
  const primaryKeys     = primaryFamilies.map(p => p.family)
  const extraKeys       = (game.mechanicFamilies ?? []).filter(k => isExtra(k))
  const supportKeys     = (game.mechanicFamilies ?? [])
    .filter(k => !isEngine(k) && !isExtra(k) && !primaryKeys.includes(k))
  const byFamily        = game.mechanicsByFamily ?? {}
  const hasEngines      = primaryFamilies.some(p => p.isEngine)

  // Mécaniques BGG non mappées (pas dans mechanicsByFamily)
  const mappedMechanics = Object.values(byFamily).flat()
  const unmappedBgg = (game.mechanics ?? []).filter(m => !mappedMechanics.includes(m))

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">

      {/* Nav */}
      <button onClick={() => navigate(-1)} className="text-sm text-stone-400 hover:text-stone-600 flex items-center gap-1">
        ← Retour
      </button>

      {/* ── Hero ──────────────────────────────────────────────────────────── */}
      <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        {game.imageUrl && (
          <div className="h-56 sm:h-72 bg-stone-100 overflow-hidden">
            <img src={game.imageUrl} alt={game.name} className="w-full h-full object-contain" />
          </div>
        )}
        <div className="p-5">

          {/* Titre + notes */}
          <div className="flex items-start justify-between gap-3 mb-4">
            <div className="min-w-0">
              <h1 className="text-2xl font-black text-stone-900 leading-tight">{game.name}</h1>
              {game.yearPublished && (
                <p className="text-stone-400 text-sm mt-0.5">{game.yearPublished}</p>
              )}
            </div>
            <div className="flex flex-col gap-1.5 flex-shrink-0 items-end">
              {game.bggUserRating != null && (
                <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 text-center min-w-[60px]">
                  <div className="text-xl font-black text-emerald-600">{game.bggUserRating}</div>
                  <div className="text-xs text-emerald-500 leading-tight">ma note</div>
                </div>
              )}
              {game.ratingBgg != null && (
                <div className="bg-amber-50 rounded-xl px-3 py-2 text-center min-w-[60px]">
                  <div className={`font-black text-amber-600 ${game.bggUserRating != null ? 'text-base' : 'text-xl'}`}>
                    {game.ratingBgg.toFixed(1)}
                  </div>
                  <div className="text-xs text-stone-400 leading-tight">/ 10 BGG</div>
                </div>
              )}
            </div>
          </div>

          {/* Infos rapides */}
          <div className="grid grid-cols-2 gap-3">
            <div className="flex items-center gap-2 bg-stone-50 rounded-xl px-3 py-2.5">
              <span className="text-lg">👥</span>
              <div>
                <div className="text-xs text-stone-400 leading-none">Joueurs</div>
                <div className="text-sm font-semibold text-stone-800">{playerRange}</div>
              </div>
            </div>
            {game.playingTime > 0 && (
              <div className="flex items-center gap-2 bg-stone-50 rounded-xl px-3 py-2.5">
                <span className="text-lg">⏱</span>
                <div>
                  <div className="text-xs text-stone-400 leading-none">Durée</div>
                  <div className="text-sm font-semibold text-stone-800">
                    {game.playingTime < 60
                      ? `${game.playingTime} min`
                      : `${Math.floor(game.playingTime / 60)}h${game.playingTime % 60 ? game.playingTime % 60 + 'min' : ''}`
                    }
                  </div>
                </div>
              </div>
            )}
            {game.complexity > 0 && (
              <div className="col-span-2 bg-stone-50 rounded-xl px-3 py-2.5">
                <div className="text-xs text-stone-400 mb-1.5">Complexité</div>
                <ComplexityBar value={game.complexity} />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ── Mécaniques Engelstein ─────────────────────────────────────────── */}
      {(primaryFamilies.length > 0 || supportKeys.length > 0) && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 space-y-4">

          {/* Familles principales */}
          {primaryFamilies.length > 0 && (
            <div>
              <div className="flex items-center gap-2 mb-3">
                <p className="text-xs font-semibold text-stone-500 uppercase tracking-wide">
                  {hasEngines
                    ? `Moteur${primaryFamilies.length > 1 ? 's' : ''} principal${primaryFamilies.length > 1 ? 'aux' : ''}`
                    : 'Style principal'}
                </p>
                {game.primaryEngine && (
                  <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">validé</span>
                )}
                <span className="text-xs text-stone-300 ml-auto">
                  {hasEngines ? 'moteur Engelstein' : 'familles dominantes'}
                </span>
              </div>
              <div className="grid grid-cols-1 gap-2">
                {primaryFamilies.map(({ family, isEngine: eng }) => (
                  <MechanicBlock
                    key={family}
                    familyKey={family}
                    bggMechanics={byFamily[family]}
                    primary={eng}
                  />
                ))}
              </div>
              {!hasEngines && (
                <p className="text-xs text-stone-400 mt-2 italic">
                  Aucun moteur Engelstein identifié — familles les plus représentées affichées.
                </p>
              )}
            </div>
          )}

          {/* Familles de support restantes */}
          {supportKeys.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">
                Mécaniques de support
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {supportKeys.map(key => (
                  <MechanicBlock
                    key={key}
                    familyKey={key}
                    bggMechanics={byFamily[key]}
                    primary={false}
                  />
                ))}
              </div>
            </div>
          )}

          {/* Familles extra (solo, real_time, dexterity, legacy) */}
          {extraKeys.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">
                Caractéristiques
              </p>
              <div className="flex flex-wrap gap-2">
                {extraKeys.map(key => (
                  <span key={key} className={`text-xs font-medium px-3 py-1.5 rounded-full border ${familyColor(key)}`}>
                    {familyLabel(key)}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Mécaniques BGG non classifiées */}
          {unmappedBgg.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-300 uppercase tracking-wide mb-2">
                Autres mécaniques BGG
              </p>
              <div className="flex flex-wrap gap-1.5">
                {unmappedBgg.map(m => (
                  <span key={m} className="text-xs bg-stone-100 text-stone-400 px-2.5 py-1 rounded-full">
                    {m}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

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
            <button
              onClick={() => setDescExpanded(v => !v)}
              className="mt-2 text-xs text-amber-600 hover:underline font-medium"
            >
              {descExpanded ? 'Voir moins ↑' : 'Lire la suite ↓'}
            </button>
          )}
        </div>
      )}

      {/* ── Catégories & mécaniques BGG ──────────────────────────────────── */}
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
                      <span key={name} className="text-xs bg-stone-50 text-stone-500 border border-stone-200 px-2.5 py-1 rounded-full">
                        {name}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            )
          })()}
          {game.mechanics?.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Mécaniques BGG</p>
              <div className="flex flex-wrap gap-1.5">
                {game.mechanics.map(m => (
                  <span key={m} className="text-xs bg-stone-100 text-stone-600 border border-stone-200 px-2.5 py-1 rounded-full">
                    {m}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── Extensions ───────────────────────────────────────────────────── */}
      {game.expansions?.length > 0 && (() => {
        // Afficher les extensions "officielles" (≥50 votants BGG) + toutes les possédées
        const visibleExps = game.expansions.filter(e => e.owned || (e.usersRated ?? 0) >= 50)
        if (visibleExps.length === 0) return null
        return (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5">
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">
            Extensions ({visibleExps.length}{game.expansions.length > visibleExps.length ? ` sur ${game.expansions.length}` : ''})
          </p>
          <div className="space-y-2">
            {visibleExps.map(exp => (
              <Link
                key={exp.id}
                to={`/jeux/${exp.id}`}
                className={`flex items-center gap-3 p-2.5 rounded-xl border transition-all hover:shadow-sm ${
                  exp.owned
                    ? 'bg-emerald-50 border-emerald-200 hover:border-emerald-300'
                    : 'bg-stone-50 border-stone-200 hover:border-stone-300'
                }`}
              >
                {exp.imageUrl
                  ? <img src={exp.imageUrl} alt={exp.name} className="w-10 h-10 rounded-lg object-cover flex-shrink-0" />
                  : <div className="w-10 h-10 rounded-lg bg-stone-200 flex items-center justify-center flex-shrink-0 text-stone-400 text-lg">🎲</div>
                }
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-stone-800 truncate">{exp.name}</span>
                    {exp.owned && (
                      <span className="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full flex-shrink-0">
                        ✓ possédée
                      </span>
                    )}
                  </div>
                  {exp.yearPublished && (
                    <span className="text-xs text-stone-400">{exp.yearPublished}</span>
                  )}
                </div>
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  {exp.ratingBgg && (
                    <span className="text-xs font-bold text-amber-600">{exp.ratingBgg.toFixed(1)}</span>
                  )}
                  <span className="text-stone-300 text-xs">›</span>
                </div>
              </Link>
            ))}
          </div>
        </div>
        )
      })()}

      {/* ── Session CTA ──────────────────────────────────────────────────── */}
      <div className="bg-amber-50 rounded-2xl border border-amber-200 p-5">
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
            <button
              onClick={() => setShowSessionForm(true)}
              className="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2.5 rounded-xl transition-all"
            >
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
                  <button
                    key={n} type="button"
                    onClick={() => setSessionForm(f => ({ ...f, playersCount: n }))}
                    className={'w-10 h-10 rounded-lg font-bold transition-all ' + (sessionForm.playersCount === n ? 'bg-amber-500 text-white' : 'bg-white text-stone-600 hover:bg-stone-100')}
                  >{n}</button>
                ))}
              </div>
            </div>
            <div>
              <p className="text-sm font-semibold text-stone-600 mb-2">Votre avis</p>
              <div className="flex gap-3">
                {[{ val: 5, icon: '👍', label: 'Aimé' }, { val: 1, icon: '👎', label: 'Pas aimé' }].map(({ val, icon, label }) => (
                  <button
                    key={val} type="button"
                    onClick={() => setSessionForm(f => ({ ...f, rating: f.rating === val ? null : val }))}
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
