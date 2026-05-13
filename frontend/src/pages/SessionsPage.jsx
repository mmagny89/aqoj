import { useState, useEffect } from 'react'
import { getSessions, createSession, getGames } from '../api'

function RatingButton({ value, selected, onClick, children }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={
        'px-4 py-2 rounded-lg text-lg font-bold transition-all ' +
        (selected ? 'bg-amber-500 text-white shadow' : 'bg-stone-100 text-stone-600 hover:bg-stone-200')
      }
    >
      {children}
    </button>
  )
}

export default function SessionsPage() {
  const [sessions, setSessions] = useState([])
  const [games, setGames] = useState([])
  const [showForm, setShowForm] = useState(false)
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState({
    gameId: '',
    playersCount: 4,
    rating: null,
    note: '',
    context: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    Promise.all([getSessions(), getGames()])
      .then(([s, g]) => {
        setSessions(s)
        setGames(g)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.gameId) return
    setSubmitting(true)
    setError(null)
    try {
      const session = await createSession({
        gameId: Number(form.gameId),
        playersCount: Number(form.playersCount),
        rating: form.rating,
        note: form.note || null,
        context: form.context || null,
      })
      setSessions((prev) => [session, ...prev])
      setShowForm(false)
      setForm({ gameId: '', playersCount: 4, rating: null, note: '', context: '' })
    } catch (err) {
      setError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  const formatDate = (iso) => {
    const d = new Date(iso)
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-3xl font-black text-stone-900">Mes parties</h2>
        <button
          onClick={() => setShowForm(true)}
          className="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-xl text-sm"
        >
          + Ajouter
        </button>
      </div>

      {showForm && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 mb-6">
          <h3 className="font-bold text-lg text-stone-800 mb-4">Enregistrer une partie</h3>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-semibold text-stone-600 mb-1">Jeu joué</label>
              <select
                value={form.gameId}
                onChange={(e) => setForm((f) => ({ ...f, gameId: e.target.value }))}
                required
                className="w-full px-3 py-2 rounded-lg border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
              >
                <option value="">Choisir un jeu…</option>
                {games.map((g) => (
                  <option key={g.id} value={g.id}>{g.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-semibold text-stone-600 mb-2">Joueurs</label>
              <div className="flex gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                  <button
                    key={n}
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, playersCount: n }))}
                    className={
                      'w-10 h-10 rounded-lg font-bold transition-all ' +
                      (form.playersCount === n
                        ? 'bg-amber-500 text-white'
                        : 'bg-stone-100 text-stone-600 hover:bg-stone-200')
                    }
                  >
                    {n}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-stone-600 mb-2">Note</label>
              <div className="flex gap-3">
                <RatingButton value={5} selected={form.rating === 5} onClick={() => setForm((f) => ({ ...f, rating: 5 }))}>
                  👍
                </RatingButton>
                <RatingButton value={1} selected={form.rating === 1} onClick={() => setForm((f) => ({ ...f, rating: 1 }))}>
                  👎
                </RatingButton>
                {form.rating && (
                  <button type="button" onClick={() => setForm((f) => ({ ...f, rating: null }))} className="text-xs text-stone-400 hover:text-stone-600 self-center">
                    effacer
                  </button>
                )}
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-stone-600 mb-1">Commentaire (optionnel)</label>
              <textarea
                value={form.note}
                onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                rows={2}
                placeholder="Comment s'est passée la partie ?"
                className="w-full px-3 py-2 rounded-lg border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"
              />
            </div>

            {error && <p className="text-red-600 text-sm">{error}</p>}

            <div className="flex gap-3">
              <button
                type="submit"
                disabled={submitting || !form.gameId}
                className="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-2.5 rounded-xl"
              >
                {submitting ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="px-4 py-2.5 bg-stone-100 text-stone-600 rounded-xl font-medium hover:bg-stone-200"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>
      )}

      {loading && (
        <div className="text-center py-12 text-stone-400">
          <div className="text-4xl mb-4">⏳</div>
          <p>Chargement…</p>
        </div>
      )}

      {!loading && sessions.length === 0 && (
        <div className="text-center py-12 text-stone-500">
          <div className="text-5xl mb-4">🎲</div>
          <p className="font-medium">Aucune partie enregistrée.</p>
          <p className="text-sm mt-2">Jouez à quelque chose et revenez noter la partie !</p>
        </div>
      )}

      {sessions.length > 0 && (
        <div className="space-y-3">
          {sessions.map((s) => (
            <div
              key={s.id}
              className="bg-white rounded-xl border border-stone-200 p-4 flex items-start gap-4"
            >
              <div className="text-2xl flex-shrink-0">
                {s.rating === 5 ? '👍' : s.rating === 1 ? '👎' : '🎲'}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <p className="font-bold text-stone-900 truncate">{s.game?.name ?? 'Jeu inconnu'}</p>
                  <span className="text-xs text-stone-400 flex-shrink-0">{formatDate(s.playedAt)}</span>
                </div>
                <p className="text-sm text-stone-500">
                  👥 {s.playersCount} joueur{s.playersCount > 1 ? 's' : ''}
                  {s.context && <> · {s.context}</>}
                </p>
                {s.note && <p className="mt-1 text-sm text-stone-600 italic">"{s.note}"</p>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
