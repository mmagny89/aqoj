import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import RecommendationPage from './RecommendationPage'

let currentUser = { email: 'a@example.test' }
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: currentUser }) }))
vi.mock('../api', () => ({ getRecommendations: vi.fn(), getUserPreferences: vi.fn(), getThemeMappings: vi.fn() }))
import { getRecommendations, getThemeMappings, getUserPreferences } from '../api'

const game = { id: 1, name: 'Azul', minPlayers: 2, maxPlayers: 4, playingTime: 40, complexity: 1.8 }
const renderAt = (url) => render(<MemoryRouter initialEntries={[url]}><RecommendationPage /></MemoryRouter>)

describe('RecommendationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    currentUser = { email: 'a@example.test' }
    getUserPreferences.mockResolvedValue(null)
    getThemeMappings.mockResolvedValue([])
    getRecommendations.mockResolvedValue([{ game, reason: 'Vous aimez ce style' }])
  })

  it('lance la recherche avec la table et la durée venues de l’accueil', async () => {
    renderAt('/recommander?joueurs=3&duree=120')

    expect(await screen.findAllByRole('link', { name: 'Azul' })).not.toHaveLength(0)
    expect(getRecommendations).toHaveBeenCalledWith(expect.objectContaining({ players: 3, maxTime: 120, scope: 'collection' }))
    expect(getRecommendations).toHaveBeenCalledWith(expect.objectContaining({ scope: 'discover' }))
  })

  it('relance la recherche avec le nombre de joueurs choisi', async () => {
    renderAt('/recommander')

    await userEvent.click(screen.getByRole('button', { name: '2' }))
    await userEvent.click(screen.getByRole('button', { name: 'Trouver des jeux' }))

    expect(getRecommendations).toHaveBeenCalledWith(expect.objectContaining({ players: 2, maxTime: 60 }))
  })

  it('part de la table enregistrée sur l’accueil quand l’adresse n’en porte pas', async () => {
    getUserPreferences.mockResolvedValue({ table: { players: 6, maxTime: 90 } })
    renderAt('/recommander')

    expect(await screen.findByRole('button', { name: '6' })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(screen.getByRole('button', { name: 'Trouver des jeux' }))

    expect(getRecommendations).toHaveBeenCalledWith(expect.objectContaining({ players: 6, maxTime: 90 }))
  })

  it('invite à se connecter sans compte', () => {
    currentUser = null
    renderAt('/recommander')

    expect(screen.getByRole('heading', { name: 'Connexion requise' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
  })
})
