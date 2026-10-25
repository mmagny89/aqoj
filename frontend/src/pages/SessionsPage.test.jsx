import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import SessionsPage from './SessionsPage'

vi.mock('../api', () => ({ getSessions: vi.fn(), createSession: vi.fn(), searchGames: vi.fn() }))
import { createSession, getSessions, searchGames } from '../api'

describe('SessionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getSessions.mockResolvedValue([{ id: 1, game: { name: 'Azul' }, playersCount: 3, rating: 5, playedAt: '2026-10-20T20:00:00+01:00', note: 'Très bien' }])
    searchGames.mockResolvedValue([{ id: 7, name: 'Catan', yearPublished: 1995 }, { id: 8, name: 'Catan : Marins', isExpansion: true }])
  })

  it('liste les parties avec leur avis', async () => {
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    expect(await screen.findByRole('heading', { name: 'Azul' })).toBeInTheDocument()
    expect(screen.getByText('Aimé')).toBeInTheDocument()
    expect(screen.getByText(/3 joueurs/)).toBeInTheDocument()
  })

  it('regroupe les parties par mois et résume le bilan, avec un lien vers la fiche du jeu', async () => {
    getSessions.mockResolvedValue([
      { id: 3, game: { id: 7, name: 'Catan' }, playersCount: 4, rating: 5, playedAt: '2026-10-20T20:00:00+01:00', note: null },
      { id: 2, game: { id: 7, name: 'Catan' }, playersCount: 2, rating: 1, playedAt: '2026-10-02T20:00:00+01:00', note: null },
      { id: 1, game: { id: 9, name: 'Azul' }, playersCount: 3, rating: null, playedAt: '2026-09-14T20:00:00+01:00', note: null },
    ])
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    expect(await screen.findByRole('heading', { name: 'octobre 2026' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'septembre 2026' })).toBeInTheDocument()
    expect(screen.getByText('3 parties')).toBeInTheDocument()
    expect(screen.getByText('2 jeux différents')).toBeInTheDocument()
    expect(screen.getByText('1 aimée')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Catan' })[0]).toHaveAttribute('href', '/games/7')
  })

  it('cherche le jeu dans toute la base, sans appeler BGG, et enregistre la partie', async () => {
    createSession.mockResolvedValue({ id: 2, game: { name: 'Catan' }, playersCount: 2, rating: 1, playedAt: '2026-10-25T20:00:00+01:00' })
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une partie' }))
    await userEvent.type(screen.getByLabelText('Jeu joué'), 'cat')
    await userEvent.click(await screen.findByRole('button', { name: /^Catan\s*1995$/ }))

    expect(searchGames).toHaveBeenCalledWith({ q: 'cat', local: true })
    expect(screen.getByText('(1995)')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: '2' }))
    await userEvent.click(screen.getByRole('button', { name: 'Pas aimé' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(createSession).toHaveBeenCalledWith(expect.objectContaining({ gameId: 7, playersCount: 2, rating: 1 }))
    expect(await screen.findByRole('heading', { name: 'Catan' })).toBeInTheDocument()
  })

  it('propose de changer de jeu et empêche d’enregistrer sans jeu', async () => {
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une partie' }))
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()

    await userEvent.type(screen.getByLabelText('Jeu joué'), 'cat')
    await userEvent.click(await screen.findByRole('button', { name: /^Catan\s*1995$/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Changer de jeu' }))

    expect(screen.getByLabelText('Jeu joué')).toBeInTheDocument()
  })
})
