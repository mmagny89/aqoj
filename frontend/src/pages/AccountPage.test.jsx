import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AccountPage from './AccountPage'

const logout = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { email: 'a@example.test' }, logout }) }))
vi.mock('../api', () => ({ exportAccount: vi.fn(), deleteAccount: vi.fn() }))
import { deleteAccount } from '../api'

describe('AccountPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('demande le mot de passe avant de supprimer, puis déconnecte', async () => {
    deleteAccount.mockResolvedValue({ deleted: true })
    render(<MemoryRouter><AccountPage /></MemoryRouter>)

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer mon compte' }))
    await userEvent.type(screen.getByLabelText(/mot de passe/i), 'secret-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }))

    await waitFor(() => expect(logout).toHaveBeenCalled())
    expect(deleteAccount).toHaveBeenCalledWith('secret-solide')
  })

  it("affiche l'erreur et garde la session si le mot de passe est refusé", async () => {
    deleteAccount.mockRejectedValue(new Error('Mot de passe incorrect.'))
    render(<MemoryRouter><AccountPage /></MemoryRouter>)

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer mon compte' }))
    await userEvent.type(screen.getByLabelText(/mot de passe/i), 'faux')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Mot de passe incorrect.')
    expect(logout).not.toHaveBeenCalled()
  })
})
