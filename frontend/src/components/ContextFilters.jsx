import { useState } from 'react'
import FilterRow from './FilterRow'
import {
  AGES, DURATIONS, FITS, NO_FILTERS, PLAYERS, SCENARIOS, WEIGHTS,
  activeCount, durationLabel, sameFilters,
} from '../utils/contexte'

function Chip({ active, onClick, children, title }) {
  return (
    <button type="button" className="chip chip-sm shrink-0" aria-pressed={active} onClick={onClick} title={title}>
      {children}
    </button>
  )
}

/**
 * Barre de filtres de contexte (joueurs, durée, poids, mode, âge, déjà joué) avec des scénarios en un clic.
 * Contrôlée : `filters` et `onChange` viennent de `useContextFilters` (l'état est dans l'adresse).
 * `withPlayed` est faux là où « joué » est déjà un onglet (ludothèque).
 */
export default function ContextFilters({ filters, onChange, withPlayed = true, idPrefix = 'ctx' }) {
  const count = activeCount(filters, { withPlayed })
  const detailsActive = activeCount({ ...filters, played: null }, { withPlayed: false }) > 0 || (withPlayed && filters.played != null)
  const [chosen, setChosen] = useState(null)
  const open = chosen ?? detailsActive
  const set = (patch) => onChange(prev => ({ ...prev, ...patch }))
  // Un chip rappuyé se désélectionne.
  const toggle = (key, value) => set({ [key]: filters[key] === value ? null : value })
  const label = 'w-24'

  const applyScenario = (scenario) => onChange(prev => (
    sameFilters({ ...NO_FILTERS, ...scenario.filters, played: prev.played }, prev)
      ? { ...NO_FILTERS, played: prev.played }
      : { ...NO_FILTERS, ...scenario.filters, played: prev.played }
  ))

  return (
    <section aria-label="Filtres de contexte" className="flex flex-col gap-2">
      <FilterRow scroll id={`${idPrefix}-scenarios`} label="Scénario" labelClass={label}>
        {SCENARIOS.map(scenario => (
          <Chip
            key={scenario.label}
            active={sameFilters({ ...NO_FILTERS, ...scenario.filters, played: filters.played }, filters)}
            onClick={() => applyScenario(scenario)}
          >
            {scenario.label}
          </Chip>
        ))}
      </FilterRow>

      <div className="flex flex-wrap items-center gap-x-3">
        <button
          type="button"
          onClick={() => setChosen(!open)}
          aria-expanded={open}
          aria-controls={`${idPrefix}-details`}
          className="chip chip-sm self-start"
        >
          Ma table{count > 0 && ` (${count})`}
          <span aria-hidden="true" className="ml-1.5">{open ? '−' : '+'}</span>
        </button>
        {count > 0 && (
          <button
            type="button"
            onClick={() => onChange(prev => ({ ...NO_FILTERS, played: withPlayed ? null : prev.played }))}
            className="inline-flex min-h-11 items-center text-sm font-bold underline underline-offset-4"
          >
            Effacer les filtres
          </button>
        )}
      </div>

      <div id={`${idPrefix}-details`} hidden={!open} className="flex flex-col gap-2">
        <FilterRow scroll id={`${idPrefix}-joueurs`} label="Joueurs" labelClass={label}>
          {PLAYERS.map(n => (
            <Chip key={n} active={filters.players === n} onClick={() => set({ players: filters.players === n ? null : n, fit: filters.players === n ? null : filters.fit })}>
              {n}{n === PLAYERS.at(-1) ? '+' : ''}
            </Chip>
          ))}
        </FilterRow>
        {filters.players != null && (
          <FilterRow scroll id={`${idPrefix}-nombre`} label={`À ${filters.players}`} labelClass={label}>
            {FITS.map(fit => (
              <Chip
                key={fit.label}
                active={filters.fit === fit.value}
                onClick={() => set({ fit: fit.value })}
                title={fit.value === 'best' ? 'Meilleur nombre de joueurs, par vote de la communauté BGG' : fit.value === 'recommended' ? 'Nombre de joueurs conseillé par la communauté BGG' : 'Le jeu se joue à ce nombre'}
              >
                {fit.label}
              </Chip>
            ))}
          </FilterRow>
        )}
        <FilterRow scroll id={`${idPrefix}-duree`} label="Durée" labelClass={label}>
          {DURATIONS.map(m => (
            <Chip key={m} active={filters.maxTime === m} onClick={() => toggle('maxTime', m)}>≤ {durationLabel(m)}</Chip>
          ))}
        </FilterRow>
        <FilterRow scroll id={`${idPrefix}-poids`} label="Poids" labelClass={label}>
          {WEIGHTS.map(w => (
            <Chip key={w.value} active={filters.weight === w.value} onClick={() => toggle('weight', w.value)} title={w.hint}>{w.label}</Chip>
          ))}
        </FilterRow>
        <FilterRow scroll id={`${idPrefix}-mode`} label="Mode" labelClass={label}>
          <Chip active={filters.coop === true} onClick={() => toggle('coop', true)}>Coopératif</Chip>
          <Chip active={filters.coop === false} onClick={() => toggle('coop', false)}>Compétitif</Chip>
        </FilterRow>
        <FilterRow scroll id={`${idPrefix}-age`} label="Plus jeune" labelClass={label}>
          {AGES.map(a => (
            <Chip key={a} active={filters.age === a} onClick={() => toggle('age', a)} title={`Jeux dont l'âge minimum est de ${a} ans au plus`}>{a} ans</Chip>
          ))}
        </FilterRow>
        {withPlayed && (
          <FilterRow scroll id={`${idPrefix}-joue`} label="Joué" labelClass={label}>
            <Chip active={filters.played === true} onClick={() => toggle('played', true)}>Déjà joué</Chip>
            <Chip active={filters.played === false} onClick={() => toggle('played', false)}>Jamais joué</Chip>
          </FilterRow>
        )}
      </div>
    </section>
  )
}
