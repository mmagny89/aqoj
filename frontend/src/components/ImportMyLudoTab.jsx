import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { analyzeMyLudo, importMyLudo } from '../api'
import { ErrorMessage } from './forms'
import GamePicker from './GamePicker'

const formatNote = (n) => String(n).replace('.', ',')

/** « Nom (2019) · 7,5/10 » : de quoi reconnaître un jeu parmi des homonymes. */
function describeGame(game) {
  return [
    game.yearPublished ? `${game.name} (${game.yearPublished})` : game.name,
    game.ratingBgg ? `${formatNote(game.ratingBgg.toFixed(1))}/10` : null,
    game.usersRated ? `${game.usersRated.toLocaleString('fr-FR')} votes` : null,
  ].filter(Boolean).join(' · ')
}

function RowTitle({ row }) {
  return (
    <div>
      <h4 className="m-0 text-lg font-bold">
        {row.title}{row.subtitle ? <span className="font-normal"> — {row.subtitle}</span> : null}
      </h4>
      <p className="m-0 text-sm text-stone-600">
        {[row.year, row.publisher, row.rating ? `votre note : ${formatNote(row.rating)}/10` : null].filter(Boolean).join(' · ')}
      </p>
    </div>
  )
}

/* ── Une ligne à rapprocher : on choisit le bon jeu, on cherche un autre, ou on laisse de côté ── */
function ReconcileRow({ row, choice, onChoose }) {
  const name = useId()
  const [searching, setSearching] = useState(row.status === 'unmatched')

  return (
    <li className="panel flex flex-col gap-3 p-4">
      <RowTitle row={row} />

      {row.status === 'ambiguous' && (
        <fieldset className="m-0 flex flex-col gap-1 border-0 p-0">
          <legend className="mb-1 font-bold">Plusieurs jeux portent ce nom : lequel est-ce ?</legend>
          {row.candidates.map(game => (
            <label key={game.id} className="flex min-h-11 cursor-pointer items-center gap-3">
              <input type="radio" name={name} checked={choice?.id === game.id}
                onChange={() => { setSearching(false); onChoose(game) }} className="h-5 w-5 accent-brand-red" />
              <span>{describeGame(game)}</span>
            </label>
          ))}
          <label className="flex min-h-11 cursor-pointer items-center gap-3">
            <input type="radio" name={name} checked={searching}
              onChange={() => { setSearching(true); onChoose(null) }} className="h-5 w-5 accent-brand-red" />
            <span>Un autre jeu : je le cherche</span>
          </label>
          <label className="flex min-h-11 cursor-pointer items-center gap-3">
            <input type="radio" name={name} checked={!searching && choice === null}
              onChange={() => { setSearching(false); onChoose(null) }} className="h-5 w-5 accent-brand-red" />
            <span>Ne pas importer ce jeu</span>
          </label>
        </fieldset>
      )}

      {(searching || row.status === 'unmatched') && (
        <>
          {row.status === 'unmatched' && (
            <p className="m-0 text-stone-600">Ce jeu n'est pas reconnu : cherchez-le dans le catalogue, ou laissez-le de côté.</p>
          )}
          <GamePicker label="Chercher dans le catalogue" value={choice} onChange={onChoose} />
        </>
      )}
    </li>
  )
}

/* ── Une ligne déjà rapprochée : on peut la décocher ou changer de jeu ── */
function MatchedRow({ row, choice, onChoose }) {
  const [changing, setChanging] = useState(false)

  return (
    <li className="flex flex-col gap-2 border-b-2 border-ink/15 py-3 last:border-b-0">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <label className="flex min-h-11 min-w-0 cursor-pointer items-center gap-3">
          <input type="checkbox" checked={choice !== null && !changing}
            onChange={(e) => onChoose(e.target.checked ? row.game : null)} disabled={changing}
            className="h-5 w-5 shrink-0 accent-brand-red" />
          <span className="min-w-0 break-words">
            <strong>{row.title}</strong>
            {row.year ? <span className="text-stone-600"> ({row.year})</span> : null}
            <span className="text-stone-600"> → {describeGame(choice ?? row.game)}</span>
          </span>
        </label>
        <button type="button" onClick={() => setChanging(v => !v)}
          className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">
          {changing ? 'Garder ce jeu' : 'Ce n’est pas ce jeu'}
        </button>
      </div>
      {changing && <GamePicker label="Chercher le bon jeu" value={choice?.id === row.game.id ? null : choice} onChange={(g) => onChoose(g ?? row.game)} />}
    </li>
  )
}

export default function ImportMyLudoTab({ target = 'me', onImported }) {
  const forAssociation = target === 'association'
  const fileId = useId()
  const [file, setFile]           = useState(null)
  const [analyzing, setAnalyzing] = useState(false)
  const [error, setError]         = useState(null)
  const [analysis, setAnalysis]   = useState(null)
  const [choices, setChoices]     = useState({})
  const [importing, setImporting] = useState(false)
  const [report, setReport]       = useState(null)

  const analyze = async (event) => {
    event.preventDefault()
    if (!file) return
    setAnalyzing(true)
    setError(null)
    setReport(null)
    try {
      const data = await analyzeMyLudo(await file.text())
      setAnalysis(data)
      // Les jeux reconnus sont cochés d'office ; les autres attendent un choix.
      setChoices(Object.fromEntries(data.rows.map(r => [r.index, r.status === 'matched' ? r.game : null])))
    } catch (err) {
      setAnalysis(null)
      setError(err.message || 'Impossible de lire ce fichier.')
    } finally {
      setAnalyzing(false)
    }
  }

  const choose = (index) => (game) => setChoices(prev => ({ ...prev, [index]: game }))

  const selected = analysis ? analysis.rows.filter(r => choices[r.index]) : []

  const doImport = async () => {
    setImporting(true)
    setError(null)
    try {
      const bilan = await importMyLudo(selected.map(r => ({ gameId: choices[r.index].id, rating: r.rating })), target)
      setReport({ ...bilan, left: analysis.rows.length - selected.length })
      onImported?.()
      setAnalysis(null)
      setFile(null)
    } catch (err) {
      setError(err.message || 'L’import a échoué.')
    } finally {
      setImporting(false)
    }
  }

  const toCheck = analysis?.rows.filter(r => r.status !== 'matched') ?? []
  const matched = analysis?.rows.filter(r => r.status === 'matched') ?? []

  return (
    <div className="flex flex-col gap-6">
      <p className="text-lg text-stone-600">
        {forAssociation ? 'Exportez la ludothèque de l’association depuis MyLudo' : 'Exportez votre ludothèque depuis MyLudo'} au format CSV,
        puis déposez le fichier ici : nous le rapprochons du catalogue, et vous tranchez pour les jeux douteux avant que rien ne soit ajouté.
      </p>

      <form onSubmit={analyze} className="panel flex flex-col gap-4 p-5 sm:p-6">
        <div>
          <label htmlFor={fileId} className="mb-1 block font-bold">Fichier CSV de MyLudo</label>
          <input id={fileId} type="file" accept=".csv,text/csv"
            onChange={(e) => { setFile(e.target.files?.[0] ?? null); setReport(null) }}
            className="block min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper p-2 text-base" />
        </div>
        <button type="submit" disabled={!file || analyzing} className="btn btn-primary w-full">
          {analyzing ? 'Analyse en cours…' : 'Analyser le fichier'}
        </button>
      </form>

      <ErrorMessage>{error}</ErrorMessage>

      {report && (
        <section className="panel flex flex-col gap-4 bg-cream p-5 sm:p-6" aria-labelledby="titre-bilan-myludo" role="status">
          <h2 id="titre-bilan-myludo" className="text-2xl">Import terminé</h2>
          <p className="m-0">
            {report.added} jeu{report.added > 1 ? 'x' : ''} ajouté{report.added > 1 ? 's' : ''}, {report.existing} déjà dans {forAssociation ? 'la ludothèque de l’association' : 'votre ludothèque'}
            {report.rated > 0 ? `, ${report.rated} note${report.rated > 1 ? 's' : ''} reprise${report.rated > 1 ? 's' : ''}` : ''}
            {report.left > 0 ? `. ${report.left} ligne${report.left > 1 ? 's' : ''} laissée${report.left > 1 ? 's' : ''} de côté.` : '.'}
          </p>
          {!forAssociation && <Link to="/ludotheque" className="btn btn-secondary self-start">Voir ma ludothèque</Link>}
        </section>
      )}

      {analysis && (
        <>
          <section className="panel flex flex-col gap-2 p-5 sm:p-6" aria-labelledby="titre-analyse">
            <h2 id="titre-analyse" className="text-2xl">Analyse du fichier</h2>
            <p className="m-0">
              {analysis.summary.total} jeux : <strong>{analysis.summary.matched}</strong> reconnus,
              {' '}<strong>{analysis.summary.ambiguous}</strong> à départager,
              {' '}<strong>{analysis.summary.unmatched}</strong> introuvables.
            </p>
          </section>

          {toCheck.length > 0 && (
            <section aria-labelledby="titre-a-verifier" className="flex flex-col gap-3">
              <h2 id="titre-a-verifier" className="text-2xl">À vérifier ({toCheck.length})</h2>
              <ul className="m-0 flex list-none flex-col gap-4 p-0">
                {toCheck.map(row => (
                  <ReconcileRow key={row.index} row={row} choice={choices[row.index] ?? null} onChoose={choose(row.index)} />
                ))}
              </ul>
            </section>
          )}

          {matched.length > 0 && (
            <details className="panel p-5 sm:p-6" open={toCheck.length === 0}>
              <summary className="cursor-pointer text-2xl font-bold">Reconnus ({matched.length})</summary>
              <ul className="m-0 mt-3 flex list-none flex-col p-0">
                {matched.map(row => (
                  <MatchedRow key={row.index} row={row} choice={choices[row.index] ?? null} onChoose={choose(row.index)} />
                ))}
              </ul>
            </details>
          )}

          <div className="flex flex-col gap-2">
            <button type="button" onClick={doImport} disabled={selected.length === 0 || importing} className="btn btn-primary w-full">
              {importing ? 'Import en cours…' : `Importer ${selected.length} jeu${selected.length > 1 ? 'x' : ''}`}
            </button>
            <p className="m-0 text-center text-stone-600">
              {analysis.rows.length - selected.length} ligne{analysis.rows.length - selected.length > 1 ? 's' : ''} resteront de côté.
            </p>
          </div>
        </>
      )}
    </div>
  )
}
