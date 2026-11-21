import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { ClearFilters, PlayedRow, ScenarioRow } from './ContextFilters'
import { useContextFilters } from '../hooks/useContextFilters'

function Harness() {
  const [filters, setFilters] = useContextFilters()
  const { search } = useLocation()
  return (
    <>
      <ScenarioRow filters={filters} onChange={setFilters} />
      <PlayedRow filters={filters} onChange={setFilters} />
      <ClearFilters filters={filters} onChange={setFilters} />
      <output data-testid="adresse">{search}</output>
    </>
  )
}
const renderBar = (entry = '/') => render(<MemoryRouter initialEntries={[entry]}><Harness /></MemoryRouter>)
const adresse = () => screen.getByTestId('adresse').textContent

describe('filtres de contexte du haut de liste', () => {
  it('applique un scénario en un clic, dans l’adresse, et le retire en rappuyant', async () => {
    renderBar()
    await userEvent.click(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' }))

    expect(adresse()).toBe('?joueurs=4&duree=60&poids=leger')
    expect(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' })).toHaveAttribute('aria-pressed', 'true')

    await userEvent.click(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' }))
    expect(adresse()).toBe('')
  })

  it('relit « joué » dans l’adresse et le garde quand on change de scénario', async () => {
    renderBar('/?joue=non')
    expect(screen.getByRole('button', { name: 'Jamais joué' })).toHaveAttribute('aria-pressed', 'true')

    await userEvent.click(screen.getByRole('button', { name: 'À deux · 30 min' }))
    expect(adresse()).toBe('?joue=non&joueurs=2&duree=30')
  })

  it('efface tous les filtres en gardant les autres paramètres de l’adresse', async () => {
    renderBar('/?q=catan&joueurs=2&poids=moyen&joue=oui')
    await userEvent.click(screen.getByRole('button', { name: 'Effacer les filtres' }))

    expect(adresse()).toBe('?q=catan')
  })
})
