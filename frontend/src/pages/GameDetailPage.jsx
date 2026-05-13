import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getGame, createSession } from '../api'

function StatBadge({ icon, label }) {
  return (
    <div className="flex flex-col items-center bg-stone-100 rounded-xl p-3 text-center">
      <span className="text-xl mb-1">{icon}</span>
      <span className="text-xs text-stone-500 leading-tight">{label}</span>
    </div>
  )
}

export default function GameDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [game, setGame] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

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

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh] text-stone-400">
        <div className="text-center">
          <div className="text-5xl animate-spin mb-4">⏳</div>
          <p>Chargement…</p>
        </div>
      </div>
    )
  }

  if (error || !game) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-16 text-center text-stone-500">
        <div className="text-5xl mb-4">😕</div>
        <p className="font-medium">{error || 'Jeu introuvable'}</p>
        <button onClick={() => navigate(-1)} className="mt-4 text-amber-600 hover:underline text-sm">
          ← Retour
        </button>
      </div>
    )
  }

  const playerRange =
    game.minPlayers === game.maxPlayers
      ? `${game.minPlayers} joueur${game.minPlayers > 1 ? 's' : ''}`
      : `${game.minPlayers}–${game.maxPlayers} joueurs`

  return (
    <div className="max-w-2xl mx-auto px-4 py-6">
      <button
        onClick={() => navigate(-1)}
        className="text-sm text-stone-400 hover:text-stone-600 mb-4 flex items-center gap-1"
      >
        ← Retour
      </button>

      {/* Hero */}
      <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden mb-6">
        {game.imageUrl && (
          <div className="h-56 sm:h-72 overflow-hidden bg-stone-100">
            <img
              src={game.imageUrl}
              alt={game.name}
              className="w-full h-full object-contain"
            />
          </div>
        )}
        <div className="p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h1 className="text-2xl font-black text-stone-900 leading-tight">{game.name}</h1>
              {game.yearPublished && (
                <p className="text-stone-400 text-sm mt-0.5">{game.yearPublished}</p>
              )}
            </div>
            {game.ratingBgg != null && (
              <div className="flex-shrink-0 bg-amber-50 rounded-xl px-3 py-2 text-center">
                <div className="text-2xl font-black text-amber-600">{game.ratingBgg.toFixed(1)}</div>
                <div className="text-xs text-stone-400">/ 10 BGG</div>
              </div>
            )}
          </div>

          {/* Stats */}
          <div className="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-4">
            <StatBadge icon="👥" label={playerRange} />
            {game.playingTime > 0 && (
              <StatBadge icon="⏱" label={`${game.playingTime} min`} />
            )}
            {game.complexity > 0 && (
              <StatBadge icon="🧠" label={`Complexité ${game.complexity.toFixed(1)}/5`} />
            )}
          </div>
        </div>
      </div>

      {/* Categories & Mechanics */}
      {(game.categories?.length > 0 || game.mechanics?.length > 0) && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 mb-6 space-y-3">
          {game.categories?.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Catégories</p>
              <div className="flex flex-wrap gap-1.5">
                {game.categories.map((c) => (
                  <span key={c} className="text-xs bg-amber-100 text-amber-800 px-2.5 py-1 rounded-full">
                    {c}
                  </span>
                ))}
              </div>
            </div>
          )}
          {game.mechanics?.length > 0 && (
            <div>
              <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Mécaniques</p>
              <div className="flex flex-wrap gap-1.5">
                {game.mechanics.slice(0, 8).map((m) => (
                  <span key={m} className="text-xs bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full">
                    {m}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Description */}
      {game.description && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 mb-6">
          <p className="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-2">Description</p>
          <p className="text-stone-600 text-sm leading-relaxed line-clamp-6">{game.description}</p>
        </div>
      )}

      {/* Session CTA */}
      <div className="bg-amber-50 rounded-2xl border border-amber-200 p-5">
        {sessionDone ? (
          <div className="text-center">
            <div className="text-3xl mb-2">✅</div>
            <p className="font-bold text-stone-800">Partie enregistrée !</p>
            <button
              onClick={() => setSessionDone(false)}
              className="mt-3 text-sm text-amber-600 hover:underline"
            >
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
                {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                  <button
                    key={n}
                    type="button"
                    onClick={() => setSessionForm((f) => ({ ...f, playersCount: n }))}
                    className={
                      'w-10 h-10 rounded-lg font-bold transition-all ' +
                      (sessionForm.playersCount === n
                        ? 'bg-amber-500 text-white'
                        : 'bg-white text-stone-600 hover:bg-stone-100')
                    }
                  >
                    {n}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <p className="text-sm font-semibold text-stone-600 mb-2">Votre avis</p>
              <div className="flex gap-3">
                {[
                  { val: 5, icon: '👍', label: "Aimé" },
                  { val: 1, icon: '👎', label: "Pas aimé" },
                ].map(({ val, icon, label }) => (
                  <button
                    key={val}
                    type="button"
                    onClick={() => setSessionForm((f) => ({ ...f, rating: f.rating === val ? null : val }))}
                    className={
                      'flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-sm transition-all ' +
                      (sessionForm.rating === val
                        ? 'bg-amber-500 text-white'
                        : 'bg-white text-stone-600 hover:bg-stone-100')
                    }
                  >
                    {icon} {label}
                  </button>
                ))}
              </div>
            </div>

            <textarea
              value={sessionForm.note}
              onChange={(e) => setSessionForm((f) => ({ ...f, note: e.target.value }))}
              placeholder="Un commentaire ? (optionnel)"
              rows={2}
              className="w-full px-3 py-2 rounded-xl border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none bg-white"
            />

            <div className="flex gap-3">
              <button
                type="submit"
                disabled={submitting}
                className="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-2.5 rounded-xl"
              >
                {submitting ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button
                type="button"
                onClick={() => setShowSessionForm(false)}
                className="px-4 py-2.5 bg-white text-stone-600 rounded-xl font-medium hover:bg-stone-100"
              >
                Annuler
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
