import { useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import ImportBggTab from '../components/ImportBggTab'
import ImportMyLudoTab from '../components/ImportMyLudoTab'

const TABS = [
  { key: 'bgg', label: 'Compte BGG' },
  { key: 'myludo', label: 'Ma collection MyLudo' },
  { key: 'parties', label: 'Mes parties MyLudo' },
]

function ComingSoon({ children }) {
  return <p className="panel p-6 text-stone-700">{children}</p>
}

export default function ImportPage() {
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

      <div role="tablist" aria-label="Source de l'import" onKeyDown={onKeyDown} className="flex gap-2 overflow-x-auto pb-1">
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
            className={`chip shrink-0 ${active === key ? 'is-on' : ''}`}
          >
            {label}
          </button>
        ))}
      </div>

      <div id="import-panneau" role="tabpanel" aria-labelledby={`import-${active}`}>
        {active === 'bgg' && <ImportBggTab />}
        {active === 'myludo' && <ImportMyLudoTab />}
        {active === 'parties' && <ComingSoon>L'import de vos parties MyLudo viendra plus tard.</ComingSoon>}
      </div>
    </div>
  )
}
