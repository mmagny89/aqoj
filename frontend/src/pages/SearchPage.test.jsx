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

  it('propose les filtres de style tant que rien n’est saisi', async () => {
    render(<MemoryRouter><SearchPage /></MemoryRouter>)

    expect(screen.getByRole('heading', { name: 'Parcourir par style de jeu' })).toBeInTheDocument()
    await userEvent.click(screen.getAllByRole('button', { pressed: false })[0])

    expect(searchGames).toHaveBeenCalled()
  })
})
