import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { analyzeMyLudo, importMyLudo } from '../api'
import { ErrorMessage } from './forms'
import { MatchedRow, ReconcileRow } from './MyLudoReconcile'

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
