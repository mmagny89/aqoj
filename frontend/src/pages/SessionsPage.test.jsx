import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import SessionsPage from './SessionsPage'

vi.mock('../api', () => ({ getSessions: vi.fn(), getSessionsSummary: vi.fn(), getSessionsStats: vi.fn(), createSession: vi.fn(), deleteSession: vi.fn(), searchGames: vi.fn() }))
import { createSession, deleteSession, getSessions, getSessionsStats, getSessionsSummary, searchGames } from '../api'

describe('SessionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getSessionsSummary.mockResolvedValue({ plays: 1, games: 1, liked: 1 })
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
    getSessionsSummary.mockResolvedValue({ plays: 3, games: 2, liked: 1 })
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

  it('annonce le bilan de toutes les parties et charge les plus anciennes à la demande', async () => {
    const partie = (id, jour) => ({ id, game: { id: id, name: `Jeu ${id}` }, playersCount: 2, rating: null, playedAt: `2026-09-${jour}T12:00:00+00:00`, note: null })
    const page1 = Array.from({ length: 50 }, (_, i) => partie(i + 1, '20'))
    getSessionsSummary.mockResolvedValue({ plays: 2000, games: 300, liked: 120 })
    getSessions.mockResolvedValueOnce(page1).mockResolvedValueOnce([partie(51, '01')])
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    expect(await screen.findByText('2000 parties')).toBeInTheDocument()
    expect(screen.getByText('300 jeux différents')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Jeu 51' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Voir les parties plus anciennes' }))

    expect(getSessions).toHaveBeenLastCalledWith(expect.objectContaining({ offset: 50, limit: 50 }))
    expect(await screen.findByRole('heading', { name: 'Jeu 51' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Voir les parties plus anciennes' })).not.toBeInTheDocument()
  })

  it('identifie les parties de MyLudo et filtre par origine et par avis', async () => {
    getSessions.mockResolvedValue([
      { id: 2, game: { id: 7, name: 'Catan' }, playersCount: 4, rating: null, playedAt: '2026-10-20T20:00:00+01:00', note: null, source: 'myludo' },
      { id: 1, game: { id: 9, name: 'Azul' }, playersCount: 2, rating: 5, playedAt: '2026-10-19T20:00:00+01:00', note: null, source: 'manual' },
    ])
    getSessionsSummary.mockResolvedValue({ plays: 2, games: 2, liked: 1, myludo: 1, firstYear: 2024 })
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    expect(await screen.findByText('1 de MyLudo')).toBeInTheDocument()
    expect(screen.getAllByText('MyLudo')).toHaveLength(2) // la puce du filtre et l'étiquette de la partie de Catan

    await userEvent.click(screen.getByRole('button', { name: 'MyLudo' }))
    await waitFor(() => expect(getSessions).toHaveBeenLastCalledWith(expect.objectContaining({ source: 'myludo' })))
    expect(getSessionsSummary).toHaveBeenLastCalledWith(expect.objectContaining({ source: 'myludo' }))

    await userEvent.click(screen.getByRole('button', { name: 'Aimées' }))
    await waitFor(() => expect(getSessions).toHaveBeenLastCalledWith(expect.objectContaining({ source: 'myludo', rating: 'liked' })))

    await userEvent.click(screen.getByRole('button', { name: 'Réinitialiser les filtres' }))
    await waitFor(() => expect(getSessions).toHaveBeenLastCalledWith(expect.objectContaining({ source: '', rating: '' })))
  })

  it('cherche une partie par mot, après la frappe', async () => {
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)
    await userEvent.type(await screen.findByLabelText('Rechercher une partie'), 'azul')

    await waitFor(() => expect(getSessions).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'azul' })))
  })

  it('affiche les statistiques : joueurs par jeu, par nombre de joueurs et par année', async () => {
    getSessionsStats.mockResolvedValue({
      averagePlayers: 3.5,
      games: [{ id: 7, name: 'Catan', plays: 5, averagePlayers: 3.4, minPlayers: 2, maxPlayers: 4, lastPlayedAt: '2026-10-20T20:00:00+01:00', liked: 2, byPlayers: [{ players: 2, plays: 1 }, { players: 4, plays: 4 }] }],
      byPlayers: [{ players: 2, plays: 1 }, { players: 4, plays: 4 }],
      byYear: [{ year: 2026, plays: 5 }],
    })
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('tab', { name: 'Statistiques' }))

    expect(await screen.findByRole('heading', { name: 'Catan' })).toBeInTheDocument()
    expect(screen.getByText('5 parties')).toBeInTheDocument()
    expect(screen.getByText(/3,4 joueurs en moyenne \(de 2 à 4\)/)).toBeInTheDocument()
    expect(screen.getByText('4 joueurs : 4×')).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: '2026 : 5 parties' })).toBeInTheDocument()
  })

  it('supprime une partie après confirmation, et la garde si on annule', async () => {
    getSessions.mockResolvedValue([
      { id: 1, game: { id: 7, name: 'Azul' }, playersCount: 3, rating: 5, playedAt: '2026-10-20T20:00:00+01:00', note: null },
      { id: 2, game: { id: 8, name: 'Catan' }, playersCount: 4, rating: null, playedAt: '2026-10-19T20:00:00+01:00', note: null },
    ])
    deleteSession.mockResolvedValue(undefined)
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('button', { name: /Supprimer la partie de Azul/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))
    expect(deleteSession).not.toHaveBeenCalled()
    expect(screen.getByRole('heading', { name: 'Azul' })).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Supprimer la partie de Azul/ }))
    expect(screen.getByText('Supprimer cette partie ?')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Oui, supprimer' }))

    expect(deleteSession).toHaveBeenCalledWith(1)
    await waitFor(() => expect(screen.queryByRole('heading', { name: 'Azul' })).not.toBeInTheDocument())
    expect(screen.getByRole('heading', { name: 'Catan' })).toBeInTheDocument()
  })

  it('ne propose pas de supprimer une partie importée de MyLudo', async () => {
    getSessions.mockResolvedValue([
      { id: 1, game: { id: 7, name: 'Azul' }, playersCount: 3, rating: null, playedAt: '2026-10-20T20:00:00+01:00', note: null, source: 'myludo' },
      { id: 2, game: { id: 8, name: 'Catan' }, playersCount: 4, rating: null, playedAt: '2026-10-19T20:00:00+01:00', note: null, source: 'manual' },
    ])
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    expect(await screen.findByRole('button', { name: /Supprimer la partie de Catan/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Supprimer la partie de Azul/ })).not.toBeInTheDocument()
  })

  it('signale l’échec d’une suppression sans retirer la partie', async () => {
    getSessions.mockResolvedValue([{ id: 1, game: { id: 7, name: 'Azul' }, playersCount: 3, rating: null, playedAt: '2026-10-20T20:00:00+01:00', note: null }])
    deleteSession.mockRejectedValue(new Error('Partie introuvable.'))
    render(<MemoryRouter><SessionsPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('button', { name: /Supprimer la partie de Azul/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Oui, supprimer' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Partie introuvable.')
    expect(screen.getByRole('heading', { name: 'Azul' })).toBeInTheDocument()
  })
})
