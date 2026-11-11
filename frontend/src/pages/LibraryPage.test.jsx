import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import LibraryPage from './LibraryPage'
import { getLibrary, getUserPreferences } from '../api'

const auth = vi.hoisted(() => ({ value: { user: { id: 1 }, loading: false } }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => auth.value }))
vi.mock('../api', () => ({ getLibrary: vi.fn(), getUserPreferences: vi.fn(), recomputeUserPreferences: vi.fn() }))

describe('LibraryPage : vos styles de jeu', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // jsdom n'a pas d'observateur d'intersection : la liste infinie n'est pas l'objet de ces tests.
    vi.stubGlobal('IntersectionObserver', class { observe() {} unobserve() {} disconnect() {} })
    getLibrary.mockResolvedValue({ games: [], total: 0, hasMore: false, userRatings: {}, bggUsername: null })
  })

  const prefs = { topEngines: ['worker_placement'], topSupport: [], topCategories: ['Economic'], updatedAt: '2026-10-25T10:00:00+00:00' }

  it('dit combien de jeux ont servi au calcul quand une partie de la collection MyLudo n’est pas encore enrichie', async () => {
    getUserPreferences.mockResolvedValue({ ...prefs, coverage: { total: 40, analysed: 12 } })
    render(<MemoryRouter><LibraryPage /></MemoryRouter>)

    expect(await screen.findByRole('heading', { name: 'Vos styles de jeu' })).toBeInTheDocument()
    expect(screen.getByText(/BGG ou MyLudo/)).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('12 jeux sur 40 analysés')
  })

  it('ne dit rien quand toute la collection est analysée', async () => {
    getUserPreferences.mockResolvedValue({ ...prefs, coverage: { total: 5, analysed: 5 } })
    render(<MemoryRouter><LibraryPage /></MemoryRouter>)

    await screen.findByRole('heading', { name: 'Vos styles de jeu' })
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
  })

  it('garde le bloc visible, avec son explication, quand rien n’est encore analysé', async () => {
    getUserPreferences.mockResolvedValue({ topEngines: [], topSupport: [], topCategories: [], coverage: { total: 8, analysed: 0 } })
    render(<MemoryRouter><LibraryPage /></MemoryRouter>)

    expect(await screen.findByRole('note')).toHaveTextContent('0 jeu sur 8 analysé')
  })

  it('accorde « jeux » au pluriel, sans « jeus »', async () => {
    getUserPreferences.mockResolvedValue(null)
    getLibrary.mockResolvedValue({ games: [], total: 230, hasMore: false, userRatings: {}, bggUsername: null })
    render(<MemoryRouter><LibraryPage /></MemoryRouter>)

    expect(await screen.findByText('230 jeux')).toBeInTheDocument()
    expect(screen.queryByText(/jeus/)).not.toBeInTheDocument()
  })
})
