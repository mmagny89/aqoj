import { useState, useEffect } from 'react'
import { getSessions, createSession } from '../api'
import GamePicker from '../components/GamePicker'

const RATINGS = [
  { value: 5, label: 'Aimé', style: 'bg-brand-green text-paper' },
  { value: 1, label: 'Pas aimé', style: 'bg-brand-red' },
]

const field = 'min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper px-3 text-base'

export default function SessionsPage() {
  const [sessions, setSessions] = useState([])
  const [showForm, setShowForm] = useState(false)
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState({
    game: null,
    playersCount: 4,
    rating: null,
    note: '',
    context: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    getSessions()
      .then(setSessions)
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.game) return
    setSubmitting(true)
    setError(null)
    try {
      const session = await createSession({
        gameId: form.game.id,
        playersCount: Number(form.playersCount),
        rating: form.rating,
        note: form.note || null,
        context: form.context || null,
      })
      setSessions((prev) => [session, ...prev])
      setShowForm(false)
      setForm({ game: null, playersCount: 4, rating: null, note: '', context: '' })
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
    <div className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-4xl leading-tight sm:text-5xl">Mes parties</h1>
          <p className="mt-1 text-lg text-stone-600">Ce que vous avez joué, et ce que vous en avez pensé.</p>
        </div>
        {!showForm && (
          <button type="button" onClick={() => setShowForm(true)} className="btn btn-primary">
            Ajouter une partie
          </button>
        )}
      </div>

      {showForm && (
        <section aria-labelledby="titre-form" className="panel p-5 sm:p-6">
          <h2 id="titre-form" className="mb-4 text-2xl">Enregistrer une partie</h2>
          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <GamePicker value={form.game} onChange={(game) => setForm((f) => ({ ...f, game }))} />

            <fieldset className="m-0 border-0 p-0">
              <legend className="mb-2 font-bold">Joueurs</legend>
              <div className="flex flex-wrap gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                  <button
                    key={n}
                    type="button"
                    aria-pressed={form.playersCount === n}
                    onClick={() => setForm((f) => ({ ...f, playersCount: n }))}
                    className="chip min-w-11 justify-center"
                  >
                    {n}
                  </button>
                ))}
              </div>
            </fieldset>

            <fieldset className="m-0 border-0 p-0">
              <legend className="mb-2 font-bold">Votre avis</legend>
              <div className="flex flex-wrap items-center gap-2">
                {RATINGS.map(({ value, label, style }) => (
                  <button
                    key={value}
                    type="button"
                    aria-pressed={form.rating === value}
                    onClick={() => setForm((f) => ({ ...f, rating: value }))}
                    className={`chip ${form.rating === value ? style : ''}`}
                  >
                    {label}
                  </button>
                ))}
                {form.rating && (
                  <button type="button" onClick={() => setForm((f) => ({ ...f, rating: null }))} className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">
                    Effacer
                  </button>
                )}
              </div>
            </fieldset>

            <div>
              <label htmlFor="partie-note" className="mb-1 block font-bold">Commentaire <span className="font-normal text-stone-600">(facultatif)</span></label>
              <textarea
                id="partie-note"
                value={form.note}
                onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                rows={3}
                placeholder="Comment s'est passée la partie ?"
                className={`${field} py-2`}
              />
            </div>

            {error && <p role="alert" className="rounded-xl border-[3px] border-brand-red bg-amber-100 p-3 font-bold">{error}</p>}

            <div className="flex flex-wrap gap-3">
              <button type="submit" disabled={submitting || !form.game} className="btn btn-primary flex-1 sm:flex-none sm:px-8">
                {submitting ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button type="button" onClick={() => setShowForm(false)} className="btn bg-paper">
                Annuler
              </button>
            </div>
          </form>
        </section>
      )}

      {loading && (
        <p role="status" className="flex items-center justify-center gap-3 py-12 text-stone-600">
          <span className="h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
          Chargement…
        </p>
      )}

      {!loading && sessions.length === 0 && (
        <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
          <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
          <h2 className="text-2xl">Aucune partie enregistrée</h2>
          <p className="text-stone-600">Jouez à quelque chose, puis revenez noter la partie.</p>
        </section>
      )}

      {sessions.length > 0 && (
        <ul className="m-0 flex list-none flex-col gap-4 p-0">
          {sessions.map((s) => {
            const rating = RATINGS.find(r => r.value === s.rating)
            return (
              <li key={s.id} className="panel flex flex-col gap-1 p-4 sm:p-5">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <h2 className="min-w-0 break-words font-sans text-lg font-bold">{s.game?.name ?? 'Jeu inconnu'}</h2>
                  <time dateTime={s.playedAt} className="shrink-0 text-sm text-stone-600">{formatDate(s.playedAt)}</time>
                </div>
                <p className="flex flex-wrap items-center gap-2 text-stone-600">
                  <span>{s.playersCount} joueur{s.playersCount > 1 ? 's' : ''}{s.context && <> · {s.context}</>}</span>
                  {rating && <span className={`badge ${rating.style}`}>{rating.label}</span>}
                </p>
                {s.note && <p className="mt-1 italic text-stone-700">« {s.note} »</p>}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
