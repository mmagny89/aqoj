// Rangées de filtres de contexte posées en haut de la liste de « À quoi on joue » ; les autres réglages sont dans sa barre latérale.
import FilterRow from './FilterRow'
import { NO_FILTERS, SCENARIOS, activeCount, sameFilters } from '../utils/contexte'

function Chip({ active, onClick, children, title }) {
  return (
    <button type="button" className="chip chip-sm shrink-0" aria-pressed={active} onClick={onClick} title={title}>
      {children}
    </button>
  )
}

const LABEL = 'w-24'

/** Rangée des scénarios en un clic ; rappuyer sur le scénario actif l'efface. `played` est conservé : ce n'est pas un réglage de table. */
export function ScenarioRow({ filters, onChange, idPrefix = 'ctx' }) {
  const apply = (scenario) => onChange(prev => (
    sameFilters({ ...NO_FILTERS, ...scenario.filters, played: prev.played }, prev)
      ? { ...NO_FILTERS, played: prev.played }
      : { ...NO_FILTERS, ...scenario.filters, played: prev.played }
  ))
  return (
    <FilterRow scroll id={`${idPrefix}-scenarios`} label="Scénario" labelClass={LABEL}>
      {SCENARIOS.map(scenario => (
        <Chip
          key={scenario.label}
          active={sameFilters({ ...NO_FILTERS, ...scenario.filters, played: filters.played }, filters)}
          onClick={() => apply(scenario)}
        >
          {scenario.label}
        </Chip>
      ))}
    </FilterRow>
  )
}

/** Rangée « Déjà joué / Jamais joué ». */
export function PlayedRow({ filters, onChange, idPrefix = 'ctx' }) {
  const toggle = (value) => onChange(prev => ({ ...prev, played: prev.played === value ? null : value }))
  return (
    <FilterRow scroll id={`${idPrefix}-joue`} label="Joué" labelClass={LABEL}>
      <Chip active={filters.played === true} onClick={() => toggle(true)}>Déjà joué</Chip>
      <Chip active={filters.played === false} onClick={() => toggle(false)}>Jamais joué</Chip>
    </FilterRow>
  )
}

/** Bouton « Effacer les filtres » : retire tous les réglages de contexte. */
export function ClearFilters({ filters, onChange }) {
  if (activeCount(filters) === 0) return null
  return (
    <button
      type="button"
      onClick={() => onChange({ ...NO_FILTERS })}
      className="inline-flex min-h-11 items-center text-sm font-bold underline underline-offset-4"
    >
      Effacer les filtres
    </button>
  )
}

/**
 * Jauge à crans : `steps` liste les valeurs (la première, null, est « pas de filtre »), `value` est l'une d'elles.
 * `format(value)` donne le texte affiché au-dessus ; `marks` les repères sous la jauge.
 */
export function Gauge({ label, steps, value, onChange, format, marks = [] }) {
  const index = Math.max(0, steps.indexOf(value))
  return (
    <div>
      <p className="text-center font-display text-xl leading-none" aria-hidden="true">{format(value)}</p>
      <input
        type="range"
        min={0}
        max={steps.length - 1}
        step={1}
        value={index}
        aria-label={label}
        aria-valuetext={format(value)}
        onChange={e => onChange(steps[Number(e.target.value)])}
        className="h-9 w-full accent-brand-red"
      />
      {marks.length > 0 && (
        <div aria-hidden="true" className="flex justify-between text-xs text-stone-600">
          {marks.map(mark => <span key={mark}>{mark}</span>)}
        </div>
      )}
    </div>
  )
}
