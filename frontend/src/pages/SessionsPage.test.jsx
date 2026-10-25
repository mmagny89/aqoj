import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import SessionsPage from './SessionsPage'

vi.mock('../api', () => ({ getSessions: vi.fn(), createSession: vi.fn(), getGames: vi.fn() }))
import { createSession, getGames, getSessions } from '../api'

describe('SessionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getSessions.mockResolvedValue([{ id: 1, game: { name: 'Azul' }, playersCount: 3, rating: 5, playedAt: '2026-10-20T20:00:00+01:00', note: 'Très bien' }])
    getGames.mockResolvedValue([{ id: 7, name: 'Catan' }])
  })

  it('liste les parties avec leur avis', async () => {
    render(<SessionsPage />)

    expect(await screen.findByRole('heading', { name: 'Azul' })).toBeInTheDocument()
    expect(screen.getByText('Aimé')).toBeInTheDocument()
    expect(screen.getByText('3 joueurs')).toBeInTheDocument()
  })

  it('enregistre une partie depuis le formulaire', async () => {
    createSession.mockResolvedValue({ id: 2, game: { name: 'Catan' }, playersCount: 2, rating: 1, playedAt: '2026-10-25T20:00:00+01:00' })
    render(<SessionsPage />)

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une partie' }))
    await userEvent.selectOptions(screen.getByLabelText('Jeu joué'), '7')
    await userEvent.click(screen.getByRole('button', { name: '2' }))
    await userEvent.click(screen.getByRole('button', { name: 'Pas aimé' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(createSession).toHaveBeenCalledWith(expect.objectContaining({ gameId: 7, playersCount: 2, rating: 1 }))
    expect(await screen.findByRole('heading', { name: 'Catan' })).toBeInTheDocument()
  })
})
