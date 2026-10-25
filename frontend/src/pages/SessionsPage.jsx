import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getSessions, getSessionsSummary, createSession } from '../api'
import GamePicker from '../components/GamePicker'
import { coverColor, initials } from '../components/GameCard'
import { gameImage } from '../utils/game'

const RATINGS = [
  { value: 5, label: 'Aimé', style: 'bg-brand-green text-paper' },
  { value: 1, label: 'Pas aimé', style: 'bg-brand-red' },
]

const field = 'min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper px-3 text-base'

export default function SessionsPage() {
  const PAGE = 50
  const [sessions, setSessions] = useState([])
  const [summary, setSummary] = useState(null)
  const [hasMore, setHasMore] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
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
    getSessions({ limit: PAGE })
      .then(list => { setSessions(list); setHasMore(list.length === PAGE) })
      .catch(() => {})
      .finally(() => setLoading(false))
    getSessionsSummary().then(setSummary).catch(() => {})
  }, [])

  const loadMore = async () => {
    setLoadingMore(true)
    try {
      const list = await getSessions({ offset: sessions.length, limit: PAGE })
      setSessions(prev => [...prev, ...list])
      setHasMore(list.length === PAGE)
    } catch {
      /* la liste déjà affichée reste valable ; le bouton permet de réessayer */
    } finally {
      setLoadingMore(false)
    }
  }

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
      getSessionsSummary().then(setSummary).catch(() => {})
      setShowForm(false)
      setForm({ game: null, playersCount: 4, rating: null, note: '', context: '' })
    } catch (err) {
      setError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  const formatDay = (iso) => new Date(iso).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
  const monthOf   = (iso) => new Date(iso).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })

  // Regroupées par mois, les plus récentes d'abord (l'API les rend déjà triées).
  const months = []
  for (const s of sessions) {
    const label = monthOf(s.playedAt)
    const last = months[months.length - 1]
    if (last?.label === label) last.items.push(s)
    else months.push({ label, items: [s] })
  }
  // Le bilan porte sur toutes les parties, pas seulement la page affichée ; à défaut, sur ce qu'on a chargé.
  const totalPlays    = summary?.plays ?? sessions.length
  const distinctGames = summary?.games ?? new Set(sessions.map(s => s.game?.id ?? s.game?.name)).size
  const liked         = summary?.liked ?? sessions.filter(s => s.rating === 5).length

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
        <>
          <p className="m-0 flex flex-wrap items-center gap-2" aria-label="Bilan">
            <span className="badge bg-cream">{totalPlays} partie{totalPlays > 1 ? 's' : ''}</span>
            <span className="badge bg-cream">{distinctGames} jeu{distinctGames > 1 ? 'x' : ''} différent{distinctGames > 1 ? 's' : ''}</span>
            {liked > 0 && <span className="badge bg-brand-green text-paper">{liked} aimée{liked > 1 ? 's' : ''}</span>}
          </p>

          {months.map(({ label, items }) => (
            <section key={label} aria-labelledby={`mois-${label.replace(/\s/g, '-')}`}>
              <h2 id={`mois-${label.replace(/\s/g, '-')}`} className="mb-2 text-2xl capitalize">{label}</h2>
              <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
                {items.map((s) => {
                  const rating = RATINGS.find(r => r.value === s.rating)
                  const image = s.game ? gameImage(s.game) : null
                  return (
                    <li key={s.id} className="panel flex items-start gap-3 p-3">
                      {image ? (
                        <img src={image} alt="" loading="lazy" className="h-14 w-14 shrink-0 rounded-lg border-2 border-ink object-cover" />
                      ) : (
                        <span aria-hidden="true" className={`grid h-14 w-14 shrink-0 place-items-center rounded-lg border-2 border-ink font-display text-xl ${s.game ? coverColor(s.game) : 'bg-cream'}`}>
                          {s.game ? initials(s.game.name) : '?'}
                        </span>
                      )}
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                          <h3 className="min-w-0 break-words font-sans text-base font-bold leading-snug">
                            {s.game?.id ? (
                              <Link to={`/games/${s.game.id}`} className="inline-block py-0.5 underline-offset-4 hover:underline">{s.game.name}</Link>
                            ) : (s.game?.name ?? 'Jeu inconnu')}
                          </h3>
                          {rating && <span className={`badge shrink-0 ${rating.style}`}>{rating.label}</span>}
                        </div>
                        <p className="m-0 text-sm text-stone-600">
                          <time dateTime={s.playedAt}>{formatDay(s.playedAt)}</time>
                          {' · '}{s.playersCount} joueur{s.playersCount > 1 ? 's' : ''}{s.context && <> · {s.context}</>}
                        </p>
                        {s.note && <p className="m-0 mt-1 text-sm italic text-stone-700">« {s.note} »</p>}
                      </div>
                    </li>
                  )
                })}
              </ul>
            </section>
          ))}

          {hasMore && (
            <button type="button" onClick={loadMore} disabled={loadingMore} className="btn bg-paper self-center">
              {loadingMore ? 'Chargement…' : 'Voir les parties plus anciennes'}
            </button>
          )}
        </>
      )}
    </div>
  )
}
