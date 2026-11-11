import { useEffect, useState } from 'react'
import { getAdminGame, getAdminGameFilters, getAdminGames, getAdminJobs, killAdminJob, stopAdminJob } from '../api'

const COMMANDS = {
  'app:import:catalogue': 'Import d’un fichier',
  'app:bgg:import-csv': 'Import du ZIP BGG',
  'app:bgg:enrich-pending': 'Enrichissement BGG',
  'app:bgg:sync-new': 'Nouveautés BGG',
  'app:bgg:relink-expansions': 'Liens des extensions',
  'app:maintenance:purge': 'Ménage hebdomadaire',
  'app:translate:descriptions': 'Traduction des descriptions',
}

const STATUS = {
  success: { label: 'Réussie', style: 'bg-green-100 text-green-700' },
  failed: { label: 'Échec', style: 'bg-red-100 text-red-700' },
  skipped: { label: 'Sautée', style: 'bg-amber-100 text-amber-700' },
  running: { label: 'En cours', style: 'bg-blue-100 text-blue-700' },
  interrupted: { label: 'Interrompue', style: 'bg-stone-200 text-stone-600' },
  cancelled: { label: 'Arrêtée', style: 'bg-stone-200 text-stone-700' },
}

const FILTERS = [
  ['all', 'Tous'],
  ['pending', 'À enrichir'],
  ['never_attempted', 'Jamais demandés à BGG'],
  ['retry_later', 'Demandés cette semaine'],
  ['unanswered', 'Sans réponse de BGG'],
  ['enriched', 'Enrichis'],
  ['stale', 'À rafraîchir'],
  ['no_image', 'Sans image'],
  ['no_description', 'Sans description'],
  ['no_mechanics', 'Sans mécanique'],
  ['unlinked_expansions', 'Extensions sans jeu de base'],
]

function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function fmtDuration(seconds) {
  if (seconds == null) return '—'
  if (seconds < 60) return `${seconds} s`
  const minutes = Math.floor(seconds / 60)
  return minutes < 60 ? `${minutes} min ${seconds % 60} s` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`
}

function StatusBadge({ status }) {
  const { label, style } = STATUS[status] ?? { label: status, style: 'bg-stone-100 text-stone-600' }
  return <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${style}`}>{label}</span>
}

function Metrics({ metrics }) {
  const entries = Object.entries(metrics ?? {})
  if (entries.length === 0) return null
  return (
    <dl className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
      {entries.map(([key, value]) => (
        <div key={key} className="flex gap-1">
          <dt>{key.replaceAll('_', ' ')} :</dt>
          <dd className="font-semibold text-stone-700">{String(value)}</dd>
        </div>
      ))}
    </dl>
  )
}

/**
 * Arrêt d'une tâche en cours. « Arrêter » est une demande : la tâche finit son lot, l'enregistre et rend la main.
 * « Forcer l'arrêt » (qui perd le lot en cours) n'apparaît qu'une fois la demande faite, ou pour une exécution
 * dite interrompue, et demande confirmation.
 */
function StopControls({ run, onChanged }) {
  const [busy, setBusy] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [error, setError] = useState(null)
  const name = COMMANDS[run.command] ?? run.command

  const act = async (action) => {
    setBusy(true)
    setError(null)
    try {
      await action(run.id)
      setConfirming(false)
      onChanged()
    } catch (e) {
      setError(e.message)
      onChanged()
    } finally {
      setBusy(false)
    }
  }

  const canForce = run.stopRequestedAt || run.status === 'interrupted'

  return (
    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3">
      {run.stopRequestedAt && run.status === 'running' && (
        <p role="status" className="m-0 text-xs text-stone-600">Arrêt demandé à {fmtDate(run.stopRequestedAt)} : la tâche termine son lot en cours.</p>
      )}
      {run.status === 'running' && !run.stopRequestedAt && (
        <button type="button" disabled={busy} onClick={() => act(stopAdminJob)} aria-label={`Arrêter : ${name}`}
          className="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-stone-700 hover:bg-stone-50 disabled:opacity-50">
          Arrêter
        </button>
      )}
      {canForce && !confirming && (
        <button type="button" disabled={busy} onClick={() => setConfirming(true)} aria-label={`Forcer l’arrêt : ${name}`}
          className="min-h-11 rounded-lg border border-red-300 bg-white px-3 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50">
          Forcer l’arrêt
        </button>
      )}
      {confirming && (
        <div role="group" aria-label="Confirmer l’arrêt forcé" className="flex flex-wrap items-center gap-2">
          <p className="m-0 text-xs text-red-700">Le lot en cours sera perdu ; les précédents restent acquis.</p>
          <button type="button" disabled={busy} onClick={() => act(killAdminJob)}
            className="min-h-11 rounded-lg bg-red-600 px-3 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
            Confirmer l’arrêt forcé
          </button>
          <button type="button" onClick={() => setConfirming(false)} className="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-600">
            Annuler
          </button>
        </div>
      )}
      {error && <p role="alert" className="m-0 w-full text-xs text-red-700">{error}</p>}
    </div>
  )
}

function RunCard({ run, onChanged }) {
  return (
    <article className="rounded-xl border border-stone-200 bg-white p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="font-bold text-stone-800">{COMMANDS[run.command] ?? run.command}</h3>
        <StatusBadge status={run.status} />
      </div>
      <p className="mt-1 text-xs text-stone-400">
        {fmtDate(run.startedAt)} · durée {fmtDuration(run.durationSeconds)}
      </p>
      {run.arguments && <p className="mt-1 break-all font-mono text-xs text-stone-500">{run.arguments}</p>}
      <Metrics metrics={run.metrics} />
      {run.error && <p role="alert" className="mt-2 rounded-lg bg-red-50 p-2 text-xs text-red-700">{run.error}</p>}
      {(run.status === 'running' || run.status === 'interrupted') && <StopControls run={run} onChanged={onChanged} />}
    </article>
  )
}

/** Dernière exécution de chaque tâche, puis l'historique filtrable. */
const CONSOLE = 'docker compose exec php php bin/console '

// Les tâches ne se lancent pas d'ici (le planificateur seul les déclenche) : on donne la commande à copier.
const COMMAND_HELP = [
  { title: 'Resynchroniser les jeux périmés', note: 'Chaque nuit à 1 h, 300 jeux. Pour rattraper un retard (« À resyncer »), une série plus grosse ; 0 = tous, plusieurs heures (BGG : un appel toutes les 5 s).', commands: ['app:bgg:enrich-pending --mode=resync --limit=300', 'app:bgg:enrich-pending --mode=resync --limit=0'] },
  { title: 'Enrichir les stubs CSV', note: 'Chaque nuit à 2 h, 200 jeux : premier enrichissement des jeux importés par CSV.', commands: ['app:bgg:enrich-pending --mode=pending --limit=200', 'app:bgg:enrich-pending --mode=pending --limit=0'] },
  { title: 'Nouvelles sorties BGG', note: 'Chaque nuit à 3 h : Hot List et top de l’année.', commands: ['app:bgg:sync-new'] },
  { title: 'Importer un ZIP du catalogue BGG', note: 'Chaque nuit à 4 h, depuis var/imports/bgg/. Pour un fichier précis : app:import:catalogue.', commands: ['app:bgg:import-csv', 'app:import:catalogue <fichier> --source=bgg [--include-expansions] [--dry-run]'] },
  { title: 'Relier les extensions à leur jeu de base', note: 'À la demande. --all re-télécharge aussi celles déjà liées.', commands: ['app:bgg:relink-expansions --limit=500', 'app:bgg:relink-expansions --all'] },
  { title: 'Traduire les descriptions', note: 'Toutes les 10 minutes, 60 jeux. --dry-run compte sans traduire.', commands: ['app:translate:descriptions --limit=60', 'app:translate:descriptions --dry-run'] },
  { title: 'Recalculer les familles Engelstein', note: 'Aussi dans Mappings mécaniques, bouton « Recalculer les familles ».', commands: ['app:recompute-mechanic-families'] },
  { title: 'Ménage', note: 'Dimanche 5 h : journal des tâches de plus de 90 jours, jetons périmés.', commands: ['app:maintenance:purge --dry-run', 'app:maintenance:purge'] },
]

function CommandLine({ command }) {
  const full = CONSOLE + command
  const [copied, setCopied] = useState(false)
  const copy = () => {
    navigator.clipboard?.writeText(full).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    })
  }
  return (
    <div className="flex items-center gap-2">
      <code className="min-w-0 flex-1 overflow-x-auto whitespace-nowrap rounded-lg bg-stone-100 px-2 py-1.5 text-xs text-stone-700">{full}</code>
      <button type="button" onClick={copy} className="min-h-11 shrink-0 rounded-lg border border-stone-300 bg-white px-3 text-xs font-semibold text-stone-600 hover:bg-stone-50">
        {copied ? 'Copié' : 'Copier'}
      </button>
    </div>
  )
}

function CommandsHelp() {
  return (
    <section aria-labelledby="commandes">
      <h3 id="commandes" className="mb-3 text-xs font-bold uppercase tracking-wide text-stone-400">Lancer une tâche à la main</h3>
      <div className="space-y-2">
        {COMMAND_HELP.map(({ title, note, commands }) => (
          <details key={title} className="rounded-xl border border-stone-200 bg-white p-3">
            <summary className="min-h-6 cursor-pointer text-sm font-semibold text-stone-700">{title}</summary>
            <p className="mb-2 mt-2 text-xs text-stone-500">{note}</p>
            <div className="space-y-2">{commands.map(c => <CommandLine key={c} command={c} />)}</div>
          </details>
        ))}
      </div>
    </section>
  )
}

export function JobsPanel() {
  const [data, setData] = useState(null)
  const [command, setCommand] = useState('')
  const [status, setStatus] = useState('')
  const [error, setError] = useState(null)

  const [reloadKey, setReloadKey] = useState(0)
  const reload = () => setReloadKey(k => k + 1)

  useEffect(() => {
    getAdminJobs({ command, status, limit: 50 }).then(setData).catch(e => setError(e.message))
  }, [command, status, reloadKey])

  // Tant qu'une tâche tourne, la liste se rafraîchit : on voit l'arrêt demandé prendre effet.
  const anyRunning = data?.runs.some(r => r.status === 'running') || data?.last.some(r => r.status === 'running')
  useEffect(() => {
    if (!anyRunning) return undefined
    const timer = setInterval(() => setReloadKey(k => k + 1), 10000)
    return () => clearInterval(timer)
  }, [anyRunning])

  if (error) return <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</p>
  if (!data) return <p className="text-sm text-stone-400">Chargement…</p>

  return (
    <div className="space-y-6">
      <section aria-labelledby="derniere-execution">
        <h3 id="derniere-execution" className="mb-3 text-xs font-bold uppercase tracking-wide text-stone-400">Dernière exécution de chaque tâche</h3>
        {data.last.length === 0
          ? <p className="text-sm text-stone-400">Aucune exécution enregistrée pour l’instant.</p>
          : <div className="grid gap-3 sm:grid-cols-2">{data.last.map(run => <RunCard key={run.command} run={run} onChanged={reload} />)}</div>}
      </section>

      <CommandsHelp />

      <section aria-labelledby="historique">
        <h3 id="historique" className="mb-3 text-xs font-bold uppercase tracking-wide text-stone-400">Historique</h3>
        <div className="mb-3 flex flex-wrap gap-3">
          <label className="text-sm text-stone-600">
            Tâche{' '}
            <select value={command} onChange={e => setCommand(e.target.value)} className="min-h-11 rounded-lg border border-stone-300 bg-white px-2">
              <option value="">Toutes</option>
              {Object.entries(COMMANDS).map(([name, label]) => <option key={name} value={name}>{label}</option>)}
            </select>
          </label>
          <label className="text-sm text-stone-600">
            Statut{' '}
            <select value={status} onChange={e => setStatus(e.target.value)} className="min-h-11 rounded-lg border border-stone-300 bg-white px-2">
              <option value="">Tous</option>
              {Object.entries(STATUS).map(([name, { label }]) => <option key={name} value={name}>{label}</option>)}
            </select>
          </label>
        </div>
        {data.runs.length === 0
          ? <p className="text-sm text-stone-400">Aucune exécution ne correspond.</p>
          : <div className="space-y-3">{data.runs.map(run => <RunCard key={run.id} run={run} onChanged={reload} />)}</div>}
      </section>
    </div>
  )
}

function GameDetail({ bggId }) {
  const [game, setGame] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    getAdminGame(bggId).then(setGame).catch(e => setError(e.message))
  }, [bggId])

  if (error) return <p role="alert" className="text-xs text-red-700">{error}</p>
  if (!game) return <p className="text-xs text-stone-400">Chargement…</p>

  const { description, ...rest } = game
  return (
    <div className="mt-3 space-y-2 rounded-lg bg-stone-50 p-3">
      <dl className="grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
        {Object.entries(rest).map(([key, value]) => (
          <div key={key} className="flex min-w-0 gap-1">
            <dt className="shrink-0 text-stone-400">{key} :</dt>
            <dd className="min-w-0 break-words font-mono text-stone-700">
              {value == null ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value)}
            </dd>
          </div>
        ))}
      </dl>
      {description && <p className="text-xs text-stone-500">{description}</p>}
    </div>
  )
}

/** Explorateur du catalogue ; le filtre est piloté par le tableau de bord (cartes cliquables). */
export function GamesPanel({ filter, onFilter }) {
  const [counts, setCounts] = useState(null)
  const [term, setTerm] = useState('')
  const [query, setQuery] = useState('')
  // La page repart de 1 quand le filtre change, y compris depuis une carte du tableau de bord.
  const [pageState, setPageState] = useState({ filter, page: 1 })
  const page = pageState.filter === filter ? pageState.page : 1
  const setPage = (next) => setPageState({ filter, page: next })
  const [result, setResult] = useState(null)
  const [openId, setOpenId] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    getAdminGameFilters().then(setCounts).catch(e => setError(e.message))
  }, [])

  useEffect(() => {
    getAdminGames({ filter, q: query, page }).then(setResult).catch(e => setError(e.message))
  }, [filter, query, page])

  const choose = (name) => { onFilter(name); setPage(1); setOpenId(null) }
  const search = (event) => { event.preventDefault(); setQuery(term.trim()); setPage(1); setOpenId(null) }

  if (error) return <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</p>

  const pages = result ? Math.max(1, Math.ceil(result.total / result.perPage)) : 1

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtres">
        {FILTERS.map(([name, label]) => (
          <button
            key={name}
            type="button"
            aria-pressed={filter === name}
            onClick={() => choose(name)}
            className={`min-h-11 rounded-full border px-3 text-sm font-medium transition-colors ${filter === name ? 'border-violet-600 bg-violet-600 text-white' : 'border-stone-300 bg-white text-stone-600 hover:bg-stone-50'}`}
          >
            {label}{counts ? ` · ${counts[name].toLocaleString('fr')}` : ''}
          </button>
        ))}
      </div>

      <form onSubmit={search} className="flex gap-2" role="search">
        <label className="sr-only" htmlFor="admin-games-q">Nom ou identifiant BGG</label>
        <input id="admin-games-q" value={term} onChange={e => setTerm(e.target.value)} placeholder="Nom ou identifiant BGG"
          className="min-h-11 min-w-0 flex-1 rounded-lg border border-stone-300 px-3 text-sm" />
        <button type="submit" className="min-h-11 rounded-lg bg-stone-800 px-4 text-sm font-semibold text-white hover:bg-stone-700">Chercher</button>
      </form>

      {!result ? <p className="text-sm text-stone-400">Chargement…</p> : (
        <>
          <p className="text-sm text-stone-500" aria-live="polite">{result.total.toLocaleString('fr')} jeu(x)</p>
          <ul className="space-y-2">
            {result.items.map(game => (
              <li key={game.bggId} className="rounded-xl border border-stone-200 bg-white p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="min-w-0">
                    <p className="truncate font-semibold text-stone-800">{game.name}</p>
                    <p className="text-xs text-stone-400">
                      BGG {game.bggId}{game.yearPublished ? ` · ${game.yearPublished}` : ''}{game.bggRank ? ` · rang ${game.bggRank}` : ''}{game.isExpansion ? ' · extension' : ''}
                    </p>
                  </div>
                  <button
                    type="button"
                    aria-expanded={openId === game.bggId}
                    onClick={() => setOpenId(openId === game.bggId ? null : game.bggId)}
                    className="min-h-11 rounded-lg border border-stone-300 px-3 text-sm text-stone-600 hover:bg-stone-50"
                  >
                    {openId === game.bggId ? 'Masquer' : 'Détail'}
                  </button>
                </div>
                <p className="mt-1 text-xs text-stone-500">
                  {game.source === 'bgg_api' ? 'Enrichi' : 'Import CSV seulement'} · synchro {fmtDate(game.lastSyncedAt)} · dernière demande BGG {fmtDate(game.lastFetchAttemptAt)}
                  {!game.hasImage ? ' · sans image' : ''}{!game.hasDescription ? ' · sans description' : ''}
                </p>
                {openId === game.bggId && <GameDetail bggId={game.bggId} />}
              </li>
            ))}
          </ul>
          <nav aria-label="Pagination" className="flex items-center justify-between gap-3">
            <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}
              className="min-h-11 rounded-lg border border-stone-300 px-4 text-sm disabled:opacity-40">Précédent</button>
            <span className="text-sm text-stone-500">Page {page} sur {pages}</span>
            <button type="button" disabled={page >= pages} onClick={() => setPage(page + 1)}
              className="min-h-11 rounded-lg border border-stone-300 px-4 text-sm disabled:opacity-40">Suivant</button>
          </nav>
        </>
      )}
    </div>
  )
}
