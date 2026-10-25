import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import AssociationLibraryPanel from '../components/AssociationLibraryPanel'
import ImportBggTab from '../components/ImportBggTab'
import ImportMyLudoTab from '../components/ImportMyLudoTab'
import ImportPlaysTab from '../components/ImportPlaysTab'

const TABS = [
  { key: 'bgg', label: 'Compte BGG' },
  { key: 'myludo', label: 'Ma collection MyLudo' },
  { key: 'parties', label: 'Mes parties MyLudo' },
]

/* Un administrateur choisit où va l'import : sa ludothèque, ou celle de l'association. */
function TargetPicker({ target, onChange }) {
  return (
    <fieldset className="panel m-0 flex flex-col gap-1 p-4">
      <legend className="float-left mb-1 w-full font-bold">Importer dans</legend>
      {[
        ['me', 'Ma ludothèque'],
        ['association', 'La ludothèque de l’association'],
      ].map(([value, label]) => (
        <label key={value} className="flex min-h-11 cursor-pointer items-center gap-3">
          <input type="radio" name="import-cible" checked={target === value} onChange={() => onChange(value)} className="h-5 w-5 accent-brand-red" />
          <span>{label}</span>
        </label>
      ))}
    </fieldset>
  )
}

export default function ImportPage() {
  const { user } = useAuth()
  const [target, setTarget] = useState('me')
  const [refreshKey, setRefreshKey] = useState(0)
  const isAdmin = user?.isAdmin === true
  // Un compte qui perd le rôle d'administrateur ne garde pas la ludothèque commune comme cible.
  const effectiveTarget = isAdmin ? target : 'me'
  const [params, setParams] = useSearchParams()
  const active = TABS.some(t => t.key === params.get('onglet')) ? params.get('onglet') : 'bgg'
  const tabRefs = useRef({})

  const select = (key) => setParams({ onglet: key }, { replace: true })

  // Flèches gauche et droite : on passe d'un onglet à l'autre en gardant le focus (modèle WAI-ARIA des onglets).
  const onKeyDown = (event) => {
    const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key]
    if (!step) return
    event.preventDefault()
    const next = TABS[(TABS.findIndex(t => t.key === active) + step + TABS.length) % TABS.length].key
    select(next)
    tabRefs.current[next]?.focus()
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div>
        <h1 className="text-4xl leading-tight sm:text-5xl">Import</h1>
        <p className="mt-1 text-lg text-stone-600">Retrouvez votre ludothèque ici, d'où qu'elle vienne.</p>
      </div>

      <div role="tablist" aria-label="Source de l'import" onKeyDown={onKeyDown} className="tab-list">
        {TABS.map(({ key, label }) => (
          <button
            key={key}
            ref={el => { tabRefs.current[key] = el }}
            type="button"
            role="tab"
            id={`import-${key}`}
            aria-selected={active === key}
            aria-controls="import-panneau"
            tabIndex={active === key ? 0 : -1}
            onClick={() => select(key)}
            className="tab"
          >
            {label}
          </button>
        ))}
      </div>

      {isAdmin && active !== 'parties' && <TargetPicker target={effectiveTarget} onChange={setTarget} />}

      <div id="import-panneau" role="tabpanel" aria-labelledby={`import-${active}`}>
        {active === 'bgg' && <ImportBggTab key={effectiveTarget} target={effectiveTarget} onImported={() => setRefreshKey(k => k + 1)} />}
        {active === 'myludo' && <ImportMyLudoTab key={effectiveTarget} target={effectiveTarget} onImported={() => setRefreshKey(k => k + 1)} />}
        {active === 'parties' && <ImportPlaysTab />}
      </div>

      {isAdmin && effectiveTarget === 'association' && active !== 'parties' && <AssociationLibraryPanel refreshKey={refreshKey} />}
    </div>
  )
}
