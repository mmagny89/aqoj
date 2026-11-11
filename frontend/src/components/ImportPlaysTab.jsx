import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { analyzeMyLudoPlays, importMyLudoPlays } from '../api'
import { ErrorMessage } from './forms'
import { MatchedRow, ReconcileRow } from './MyLudoReconcile'

const plural = (n, one, many = `${one}s`) => `${n} ${n > 1 ? many : one}`

/** Import des parties depuis l'export CSV de MyLudo : on rapproche les JEUX du fichier, puis on importe leurs parties. */
export default function ImportPlaysTab({ onImported }) {
  const fileId = useId()
  const [file, setFile]           = useState(null)
  const [csv, setCsv]             = useState('')
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
      const text = await file.text()
      const data = await analyzeMyLudoPlays(text)
      setCsv(text)
      setAnalysis(data)
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
  const playsSelected = selected.reduce((sum, r) => sum + r.plays, 0)
  const playsLeft = analysis ? analysis.summary.plays - playsSelected : 0

  const doImport = async () => {
    setImporting(true)
    setError(null)
    try {
      const mapping = Object.fromEntries(selected.map(r => [r.id, choices[r.index].id]))
      setReport(await importMyLudoPlays(csv, mapping))
      onImported?.()
      setAnalysis(null)
      setFile(null)
      setCsv('')
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
        Exportez vos parties depuis MyLudo au format CSV, puis déposez le fichier ici : nous rapprochons chaque jeu du catalogue,
        et vous tranchez pour les jeux douteux avant que rien ne soit ajouté à « Mes parties ». Rejouer le même fichier n’ajoute aucun doublon.
      </p>

      <form onSubmit={analyze} className="panel flex flex-col gap-4 p-5 sm:p-6">
        <div>
          <label htmlFor={fileId} className="mb-1 block font-bold">Fichier CSV des parties</label>
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
        <section className="panel flex flex-col gap-4 bg-cream p-5 sm:p-6" aria-labelledby="titre-bilan-parties" role="status">
          <h2 id="titre-bilan-parties" className="text-2xl">Import terminé</h2>
          <p className="m-0">
            {plural(report.imported, 'partie')} ajoutée{report.imported > 1 ? 's' : ''}
            {report.duplicates > 0 ? `, ${plural(report.duplicates, 'partie')} déjà présente${report.duplicates > 1 ? 's' : ''}` : ''}
            {report.left > 0 ? `, ${plural(report.left, 'partie')} laissée${report.left > 1 ? 's' : ''} de côté (jeu non confirmé)` : ''}
            {report.excluded > 0 ? `, ${plural(report.excluded, 'partie')} exclue${report.excluded > 1 ? 's' : ''} dans MyLudo` : ''}
            {report.invalid > 0 ? `, ${plural(report.invalid, 'partie')} sans date valide` : ''}.
          </p>
          <Link to="/parties" className="btn btn-secondary self-start">Voir mes parties</Link>
        </section>
      )}

      {analysis && (
        <>
          <section className="panel flex flex-col gap-2 p-5 sm:p-6" aria-labelledby="titre-analyse-parties">
            <h2 id="titre-analyse-parties" className="text-2xl">Analyse du fichier</h2>
            <p className="m-0">
              <strong>{analysis.summary.plays}</strong> parties de <strong>{analysis.summary.total}</strong> jeux :
              {' '}<strong>{analysis.summary.matched}</strong> reconnus,
              {' '}<strong>{analysis.summary.ambiguous}</strong> à départager,
              {' '}<strong>{analysis.summary.unmatched}</strong> introuvables.
            </p>
            {analysis.summary.already > 0 && (
              <p className="m-0 text-stone-700">{plural(analysis.summary.already, 'partie')} déjà importée{analysis.summary.already > 1 ? 's' : ''} : elles ne seront pas doublées.</p>
            )}
            {analysis.summary.excluded > 0 && (
              <p className="m-0 text-stone-700">{plural(analysis.summary.excluded, 'partie')} marquée{analysis.summary.excluded > 1 ? 's' : ''} « exclue{analysis.summary.excluded > 1 ? 's' : ''} » dans MyLudo : ignorée{analysis.summary.excluded > 1 ? 's' : ''}.</p>
            )}
          </section>

          {toCheck.length > 0 && (
            <section aria-labelledby="titre-a-verifier-parties" className="flex flex-col gap-3">
              <h2 id="titre-a-verifier-parties" className="text-2xl">À vérifier ({toCheck.length})</h2>
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
              {importing ? 'Import en cours…' : `Importer ${plural(playsSelected, 'partie')} de ${plural(selected.length, 'jeu', 'jeux')}`}
            </button>
            {playsLeft > 0 && (
              <p className="m-0 text-center text-stone-600">{plural(playsLeft, 'partie')} resteront de côté (jeux non confirmés).</p>
            )}
          </div>
        </>
      )}
    </div>
  )
}
