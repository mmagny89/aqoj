import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportBggTab from './ImportBggTab'

const login = vi.fn()
let currentUser = { email: 'a@example.test' }
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: currentUser, login }) }))
vi.mock('../api', () => ({ importBgg: vi.fn(), getMe: vi.fn(), unlinkBgg: vi.fn() }))
import { getMe, importBgg, unlinkBgg } from '../api'

describe('ImportBggTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    currentUser = { email: 'a@example.test' }
    localStorage.setItem('aqoj_token', 'jeton')
  })

  it('importe la collection et affiche le bilan', async () => {
    importBgg.mockResolvedValue({ message: 'Collection importée.', total: 12, imported: 10, skipped: 2, enriched: 0 })
    getMe.mockResolvedValue({ email: 'a@example.test', bggUsername: 'Kiki' })
    render(<MemoryRouter><ImportBggTab /></MemoryRouter>)

    await userEvent.type(screen.getByLabelText('Pseudo BoardGameGeek'), 'Kiki')
    await userEvent.click(screen.getByRole('button', { name: 'Importer ma collection' }))

    expect(await screen.findByRole('heading', { name: 'Import terminé' })).toBeInTheDocument()
    expect(importBgg).toHaveBeenCalledWith('Kiki', 'me')
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir ma ludothèque' })).toHaveAttribute('href', '/ludotheque')
  })

  it('affiche l’erreur de BoardGameGeek', async () => {
    importBgg.mockRejectedValue(new Error('Collection privée.'))
    render(<MemoryRouter><ImportBggTab /></MemoryRouter>)

    await userEvent.type(screen.getByLabelText('Pseudo BoardGameGeek'), 'Kiki')
    await userEvent.click(screen.getByRole('button', { name: 'Importer ma collection' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Collection privée.')
  })

  it('désynchronise le compte BGG et vide la ludothèque après confirmation', async () => {
    currentUser = { email: 'a@example.test', bggUsername: 'Kiki' }
    unlinkBgg.mockResolvedValue({ unlinked: true, cleared: true, removed: 7 })
    getMe.mockResolvedValue({ email: 'a@example.test', bggUsername: null })
    render(<MemoryRouter><ImportBggTab /></MemoryRouter>)

    await userEvent.click(screen.getByRole('button', { name: 'Désynchroniser' }))
    await userEvent.click(screen.getByRole('button', { name: 'Vider ma ludothèque' }))

    expect(unlinkBgg).toHaveBeenCalledWith(true)
    expect(await screen.findByRole('status')).toHaveTextContent('7 jeux retirés')
  })

  it('désynchronise en gardant la ludothèque', async () => {
    currentUser = { email: 'a@example.test', bggUsername: 'Kiki' }
    unlinkBgg.mockResolvedValue({ unlinked: true, cleared: false, removed: 0 })
    getMe.mockResolvedValue({ email: 'a@example.test', bggUsername: null })
    render(<MemoryRouter><ImportBggTab /></MemoryRouter>)

    await userEvent.click(screen.getByRole('button', { name: 'Désynchroniser' }))
    await userEvent.click(screen.getByRole('button', { name: 'Garder ma ludothèque' }))

    expect(unlinkBgg).toHaveBeenCalledWith(false)
    expect(await screen.findByRole('status')).toHaveTextContent('ludothèque conservée')
  })
})
