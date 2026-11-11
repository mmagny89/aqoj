import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import SearchPage from './SearchPage'

vi.mock('../api', () => ({ searchGames: vi.fn() }))
import { searchGames } from '../api'

const catan = { id: 616, name: 'Catan', minPlayers: 3, maxPlayers: 4, playingTime: 90, complexity: 2.3, ratingBgg: 7.1, owned: true }
const extension = { id: 700, name: 'Catan : Marins', isExpansion: true, minPlayers: 3, maxPlayers: 4, playingTime: 90, complexity: 2.3 }

describe('SearchPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    searchGames.mockResolvedValue([catan, extension])
  })

  it('cherche par nom et sépare jeux de base et extensions', async () => {
    render(<MemoryRouter initialEntries={['/rechercher?q=catan']}><SearchPage /></MemoryRouter>)

    expect(await screen.findByRole('link', { name: 'Catan' })).toHaveAttribute('href', '/games/616')
    expect(screen.queryByRole('link', { name: 'Catan : Marins' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('tab', { name: /Extensions/ }))

    expect(screen.getByRole('link', { name: 'Catan : Marins' })).toBeInTheDocument()
  })

  it('range les filtres derrière « Filtres avancés », repliés par défaut', async () => {
    render(<MemoryRouter><SearchPage /></MemoryRouter>)

    const avances = screen.getByRole('button', { name: /Filtres avancés/ })
    expect(avances).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByRole('group', { name: 'Style' })).not.toBeInTheDocument()
    await userEvent.click(avances)
    expect(avances).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('group', { name: 'Style' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Explorer le catalogue' })).toBeInTheDocument()
    await userEvent.click(screen.getAllByRole('button', { pressed: false })[0])
    expect(avances).toHaveTextContent('(1)')

    expect(searchGames).toHaveBeenCalled()
  })
})
