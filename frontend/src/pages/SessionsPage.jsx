import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getSessions, getSessionsSummary, getSessionsStats, createSession, deleteSession } from '../api'
import GamePicker from '../components/GamePicker'
import { coverColor, initials } from '../components/GameCard'
import { gameImage } from '../utils/game'

const RATINGS = [
  { value: 5, label: 'Aimé', style: 'bg-brand-green text-paper' },
  { value: 1, label: 'Pas aimé', style: 'bg-brand-red' },
]

const SOURCES = [
  { value: '', label: 'Toutes' },
  { value: 'myludo', label: 'MyLudo' },
  { value: 'manual', label: 'Saisies ici' },
]

const OPINIONS = [
  { value: '', label: 'Tous les avis' },
  { value: 'liked', label: 'Aimées' },
  { value: 'disliked', label: 'Pas aimées' },
  { value: 'none', label: 'Sans avis' },
]

const NO_FILTERS = { q: '', source: '', rating: '', players: '', year: '' }

const field = 'min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper px-3 text-base'
const plural = (n, one, many) => `${n} ${n > 1 ? many : one}`
const decimal = (n) => String(n).replace('.', ',')

export default function SessionsPage() {
  const PAGE = 50
  const [sessions, setSessions] = useState([])
  const [summary, setSummary] = useState(null)
  const [stats, setStats] = useState(null)
  const [statsStale, setStatsStale] = useState(0)
  const [tab, setTab] = useState('parties')
  const [filters, setFilters] = useState(NO_FILTERS)
  const [search, setSearch] = useState('')
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
  const [firstYear, setFirstYear] = useState(null)

  const filtered = Object.values(filters).some(v => v !== '')
  const setFilter = (key, value) => setFilters(f => ({ ...f, [key]: value }))

  // La recherche libre attend la fin de la frappe avant d'interroger l'API.
  useEffect(() => {
    const timer = setTimeout(() => setFilters(f => (f.q === search.trim() ? f : { ...f, q: search.trim() })), 300)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    getSessions({ limit: PAGE, ...filters })
      .then(list => { if (!cancelled) { setSessions(list); setHasMore(list.length === PAGE) } })
      .catch(() => {})
      .finally(() => { if (!cancelled) setLoading(false) })
    getSessionsSummary(filters)
      .then(data => { if (!cancelled) { setFirstYear(y => data.firstYear ?? y); setSummary(data) } })
      .catch(() => {})
    return () => { cancelled = true }
  }, [filters])

  useEffect(() => {
    if (tab !== 'stats') return
    let cancelled = false
    getSessionsStats(filters).then(data => { if (!cancelled) setStats(data) }).catch(() => {})
    return () => { cancelled = true }
  }, [tab, filters, statsStale])

  const refreshBilan = () => {
    getSessionsSummary(filters).then(setSummary).catch(() => {})
    setStatsStale(n => n + 1)
  }

  // Suppression en deux gestes : le bouton demande confirmation avant d'effacer la partie.
  const [confirming, setConfirming] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState(null)

  const remove = async (id) => {
    setDeleting(true)
    setDeleteError(null)
    try {
      await deleteSession(id)
      setSessions(prev => prev.filter(s => s.id !== id))
      setConfirming(null)
      refreshBilan()
    } catch (err) {
      setDeleteError(err.message || 'Impossible de supprimer cette partie.')
    } finally {
      setDeleting(false)
    }
  }

  const loadMore = async () => {
    setLoadingMore(true)
    try {
      const list = await getSessions({ offset: sessions.length, limit: PAGE, ...filters })
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
      refreshBilan()
      setShowForm(false)
      setForm({ game: null, playersCount: 4, rating: null, note: '', context: '' })
    } catch (err) {
      setError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  const resetFilters = () => { setSearch(''); setFilters(NO_FILTERS) }

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
  // Le bilan porte sur toutes les parties retenues par les filtres, pas seulement la page affichée.
  const totalPlays    = summary?.plays ?? sessions.length
  const distinctGames = summary?.games ?? new Set(sessions.map(s => s.game?.id ?? s.game?.name)).size
  const liked         = summary?.liked ?? sessions.filter(s => s.rating === 5).length
  const fromMyLudo    = summary?.myludo ?? sessions.filter(s => s.source === 'myludo').length
  const years = []
  for (let y = new Date().getFullYear(); y >= (firstYear ?? new Date().getFullYear()); y--) years.push(y)

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

      {(summary === null || summary.plays > 0 || filtered || sessions.length > 0) && (
        <section aria-label="Filtres" className="panel flex flex-col gap-4 p-4 sm:p-5">
          <div>
            <label htmlFor="parties-recherche" className="mb-1 block font-bold">Rechercher une partie</label>
            <input
              id="parties-recherche"
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Un jeu, un lieu, un mot du commentaire…"
              className={field}
            />
          </div>

          <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Origine des parties">
            {SOURCES.map(({ value, label }) => (
              <button key={label} type="button" aria-pressed={filters.source === value} onClick={() => setFilter('source', value)} className="chip chip-sm">
                {label}
              </button>
            ))}
          </div>

          <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Avis">
            {OPINIONS.map(({ value, label }) => (
              <button key={label} type="button" aria-pressed={filters.rating === value} onClick={() => setFilter('rating', value)} className="chip chip-sm">
                {label}
              </button>
            ))}
          </div>

          <div className="grid grid-cols-2 gap-3 sm:max-w-md">
            <div>
              <label htmlFor="parties-joueurs" className="mb-1 block font-bold">Joueurs</label>
              <select id="parties-joueurs" value={filters.players} onChange={(e) => setFilter('players', e.target.value)} className={field}>
                <option value="">Tous</option>
                {[1, 2, 3, 4, 5, 6, 7, 8].map(n => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="parties-annee" className="mb-1 block font-bold">Année</label>
              <select id="parties-annee" value={filters.year} onChange={(e) => setFilter('year', e.target.value)} className={field}>
                <option value="">Toutes</option>
                {years.map(y => <option key={y} value={y}>{y}</option>)}
              </select>
            </div>
          </div>

          {filtered && (
            <button type="button" onClick={resetFilters} className="inline-flex min-h-11 items-center self-start px-2 font-bold text-brand-blue underline underline-offset-4">
              Réinitialiser les filtres
            </button>
          )}
        </section>
      )}

      {loading && (
        <p role="status" className="flex items-center justify-center gap-3 py-12 text-stone-600">
          <span className="h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
          Chargement…
        </p>
      )}

      {!loading && sessions.length === 0 && !filtered && (
        <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
          <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
          <h2 className="text-2xl">Aucune partie enregistrée</h2>
          <p className="text-stone-600">Jouez à quelque chose, puis revenez noter la partie, ou importez vos parties MyLudo.</p>
        </section>
      )}

      {!loading && sessions.length === 0 && filtered && (
        <section className="panel mx-auto flex max-w-xl flex-col items-center gap-3 p-8 text-center">
          <h2 className="text-2xl">Aucune partie ne correspond</h2>
          <button type="button" onClick={resetFilters} className="btn bg-paper">Réinitialiser les filtres</button>
        </section>
      )}

      {sessions.length > 0 && (
        <>
          <p className="m-0 flex flex-wrap items-center gap-2" aria-label="Bilan">
            <span className="badge bg-cream">{plural(totalPlays, 'partie', 'parties')}</span>
            <span className="badge bg-cream">{distinctGames} jeu{distinctGames > 1 ? 'x' : ''} différent{distinctGames > 1 ? 's' : ''}</span>
            {liked > 0 && <span className="badge bg-brand-green text-paper">{liked} aimée{liked > 1 ? 's' : ''}</span>}
            {fromMyLudo > 0 && <span className="badge bg-brand-yellow">{fromMyLudo} de MyLudo</span>}
          </p>

          <div role="tablist" aria-label="Affichage" className="tab-list">
            <button type="button" role="tab" id="parties-onglet-liste" aria-selected={tab === 'parties'} aria-controls="parties-panneau" onClick={() => setTab('parties')} className="tab">
              Parties
            </button>
            <button type="button" role="tab" id="parties-onglet-stats" aria-selected={tab === 'stats'} aria-controls="parties-panneau" onClick={() => setTab('stats')} className="tab">
              Statistiques
            </button>
          </div>

          <div id="parties-panneau" role="tabpanel" aria-labelledby={tab === 'stats' ? 'parties-onglet-stats' : 'parties-onglet-liste'} className="flex flex-col gap-6">
            {tab === 'stats' ? <StatsPanel stats={stats} /> : (
              <>
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
                              <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <h3 className="min-w-0 break-words font-sans text-base font-bold leading-snug">
                                  {s.game?.id ? (
                                    <Link to={`/games/${s.game.id}`} className="inline-block py-0.5 underline-offset-4 hover:underline">{s.game.name}</Link>
                                  ) : (s.game?.name ?? 'Jeu inconnu')}
                                </h3>
                                <span className="flex shrink-0 flex-wrap gap-1.5">
                                  {s.source === 'myludo' && <span className="badge bg-brand-yellow">MyLudo</span>}
                                  {rating && <span className={`badge ${rating.style}`}>{rating.label}</span>}
                                </span>
                              </div>
                              <p className="m-0 text-sm text-stone-600">
                                <time dateTime={s.playedAt}>{formatDay(s.playedAt)}</time>
                                {' · '}{plural(s.playersCount, 'joueur', 'joueurs')}{s.context && <> · {s.context}</>}
                              </p>
                              {s.note && <p className="m-0 mt-1 line-clamp-3 text-sm italic text-stone-700">« {s.note} »</p>}
                              {s.source !== 'myludo' && (
                              <>
                                {confirming === s.id ? (
                                  <div role="group" aria-label="Confirmer la suppression" className="mt-1 flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-bold">Supprimer cette partie ?</span>
                                    <button type="button" onClick={() => remove(s.id)} disabled={deleting} className="chip chip-sm bg-brand-red">
                                      {deleting ? 'Suppression…' : 'Oui, supprimer'}
                                    </button>
                                    <button type="button" onClick={() => { setConfirming(null); setDeleteError(null) }} className="chip chip-sm">Annuler</button>
                                    {deleteError && <span role="alert" className="text-sm font-bold text-brand-red">{deleteError}</span>}
                                  </div>
                                ) : (
                                  <button type="button" onClick={() => { setConfirming(s.id); setDeleteError(null) }}
                                    aria-label={`Supprimer la partie de ${s.game?.name ?? 'ce jeu'} du ${formatDay(s.playedAt)}`}
                                    className="chip chip-sm mt-1 border-transparent bg-transparent font-normal text-stone-600 underline underline-offset-4">
                                    Supprimer
                                  </button>
                                )}
                              </>
                              )}
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
        </>
      )}
    </div>
  )
}

function Distribution({ title, rows, label }) {
  const max = Math.max(1, ...rows.map(r => r.plays))
  return (
    <section className="panel p-4 sm:p-5" aria-label={title}>
      <h3 className="mb-3 text-xl">{title}</h3>
      <ul className="m-0 flex list-none flex-col gap-2 p-0">
        {rows.map(row => (
          <li key={label(row)} className="grid grid-cols-[7rem_1fr_3rem] items-center gap-3 text-sm">
            <span className="font-bold">{label(row)}</span>
            <meter min={0} max={max} value={row.plays} aria-label={`${label(row)} : ${plural(row.plays, 'partie', 'parties')}`} className="h-4 w-full" />
            <span className="text-right tabular-nums">{row.plays}</span>
          </li>
        ))}
      </ul>
    </section>
  )
}

function StatsPanel({ stats }) {
  if (!stats) {
    return <p role="status" className="py-8 text-center text-stone-600">Calcul des statistiques…</p>
  }
  if (stats.games.length === 0) {
    return <p className="py-8 text-center text-stone-600">Aucune statistique pour ces filtres.</p>
  }
  const lastDay = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })

  return (
    <>
      {stats.averagePlayers !== null && (
        <p className="m-0 text-lg">
          En moyenne, vous jouez à <strong>{decimal(stats.averagePlayers)} joueurs</strong>.
        </p>
      )}

      <section aria-labelledby="stats-jeux">
        <h2 id="stats-jeux" className="mb-2 text-2xl">Jeux les plus joués</h2>
        <ol className="m-0 flex list-none flex-col gap-2.5 p-0">
          {stats.games.map(game => (
            <li key={game.id} className="panel flex flex-col gap-1.5 p-3">
              <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                <h3 className="min-w-0 break-words font-sans text-base font-bold leading-snug">
                  <Link to={`/games/${game.id}`} className="inline-block py-0.5 underline-offset-4 hover:underline">{game.name}</Link>
                </h3>
                <span className="badge bg-cream">{plural(game.plays, 'partie', 'parties')}</span>
              </div>
              <p className="m-0 text-sm text-stone-600">
                {game.minPlayers === game.maxPlayers
                  ? `Toujours à ${plural(game.minPlayers, 'joueur', 'joueurs')}`
                  : `${decimal(game.averagePlayers)} joueurs en moyenne (de ${game.minPlayers} à ${game.maxPlayers})`}
                {' · '}dernière le <time dateTime={game.lastPlayedAt}>{lastDay(game.lastPlayedAt)}</time>
                {game.liked > 0 && <> · {game.liked} aimée{game.liked > 1 ? 's' : ''}</>}
              </p>
              <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0" aria-label="Répartition par nombre de joueurs">
                {game.byPlayers.map(b => (
                  <li key={b.players} className="badge bg-paper">{plural(b.players, 'joueur', 'joueurs')} : {b.plays}×</li>
                ))}
              </ul>
            </li>
          ))}
        </ol>
      </section>

      <div className="grid gap-4 sm:grid-cols-2">
        <Distribution title="Par nombre de joueurs" rows={stats.byPlayers} label={r => plural(r.players, 'joueur', 'joueurs')} />
        <Distribution title="Par année" rows={stats.byYear} label={r => String(r.year)} />
      </div>
    </>
  )
}
