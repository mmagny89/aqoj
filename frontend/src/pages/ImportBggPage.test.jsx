import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportBggPage from './ImportBggPage'

const login = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { email: 'a@example.test' }, login }) }))
vi.mock('../api', () => ({ importBgg: vi.fn(), getMe: vi.fn() }))
import { getMe, importBgg } from '../api'

describe('ImportBggPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.setItem('aqoj_token', 'jeton')
  })

  it('importe la collection et affiche le bilan', async () => {
    importBgg.mockResolvedValue({ message: 'Collection importée.', total: 12, imported: 10, skipped: 2, enriched: 0 })
    getMe.mockResolvedValue({ email: 'a@example.test', bggUsername: 'Kiki' })
    render(<MemoryRouter><ImportBggPage /></MemoryRouter>)

    await userEvent.type(screen.getByLabelText('Pseudo BoardGameGeek'), 'Kiki')
    await userEvent.click(screen.getByRole('button', { name: 'Importer ma collection' }))

    expect(await screen.findByRole('heading', { name: 'Import terminé' })).toBeInTheDocument()
    expect(importBgg).toHaveBeenCalledWith('Kiki')
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir ma ludothèque' })).toHaveAttribute('href', '/ludotheque')
  })

  it('affiche l’erreur de BoardGameGeek', async () => {
    importBgg.mockRejectedValue(new Error('Collection privée.'))
    render(<MemoryRouter><ImportBggPage /></MemoryRouter>)

    await userEvent.type(screen.getByLabelText('Pseudo BoardGameGeek'), 'Kiki')
    await userEvent.click(screen.getByRole('button', { name: 'Importer ma collection' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Collection privée.')
  })
})
