import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import ContextFilters from './ContextFilters'
import { useContextFilters } from '../hooks/useContextFilters'

function Harness({ withPlayed }) {
  const [filters, setFilters] = useContextFilters()
  const { search } = useLocation()
  return (
    <>
      <ContextFilters filters={filters} onChange={setFilters} withPlayed={withPlayed} />
      <output data-testid="adresse">{search}</output>
    </>
  )
}
const renderBar = (entry = '/', props = {}) => render(<MemoryRouter initialEntries={[entry]}><Harness {...props} /></MemoryRouter>)
const adresse = () => screen.getByTestId('adresse').textContent

describe('ContextFilters', () => {
  it('applique un scénario en un clic, dans l’adresse, et le retire en rappuyant', async () => {
    renderBar()
    await userEvent.click(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' }))

    expect(adresse()).toBe('?joueurs=4&duree=60&poids=leger')
    expect(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: /^Ma table \(3\)/ })).toHaveAttribute('aria-expanded', 'true')

    await userEvent.click(screen.getByRole('button', { name: '4 joueurs · 1 h · léger' }))
    expect(adresse()).toBe('')
  })

  it('relit l’adresse partagée', () => {
    renderBar('/?joueurs=6&nombre=conseille&mode=competitif&age=10')

    expect(screen.getByRole('button', { name: '6' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: 'Conseillé' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: 'Compétitif' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: '10 ans' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('distingue jouable, conseillé et idéal une fois le nombre de joueurs choisi', async () => {
    renderBar()
    await userEvent.click(screen.getByRole('button', { name: /^Ma table/ }))
    expect(screen.queryByRole('button', { name: 'Idéal' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: '4' }))
    await userEvent.click(screen.getByRole('button', { name: 'Idéal' }))
    expect(adresse()).toBe('?joueurs=4&nombre=ideal')

    // Retirer le nombre de joueurs retire aussi l'ajustement.
    await userEvent.click(screen.getByRole('button', { name: '4' }))
    expect(adresse()).toBe('')
  })

  it('garde les autres paramètres de l’adresse et efface tous les filtres', async () => {
    renderBar('/?q=catan&joueurs=2&poids=moyen')
    await userEvent.click(screen.getByRole('button', { name: 'Effacer les filtres' }))

    expect(adresse()).toBe('?q=catan')
  })

  it('n’offre « joué » que là où ce n’est pas un onglet', () => {
    const { unmount } = renderBar('/?joueurs=2')
    expect(screen.getByRole('button', { name: 'Jamais joué' })).toBeInTheDocument()
    unmount()

    renderBar('/?joueurs=2', { withPlayed: false })
    expect(screen.queryByRole('button', { name: 'Jamais joué' })).not.toBeInTheDocument()
  })
})
