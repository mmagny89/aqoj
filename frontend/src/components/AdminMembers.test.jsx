import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import AdminMembers from './AdminMembers'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { id: 1, isAdmin: true } }) }))
vi.mock('../api', () => ({
  getInvitations: vi.fn(),
  createInvitation: vi.fn(),
  revokeInvitation: vi.fn(),
  getAdminUsers: vi.fn(),
  updateUserRole: vi.fn(),
}))
import { createInvitation, getAdminUsers, getInvitations, updateUserRole } from '../api'

const code = { id: 5, code: 'ABCDE-FGHJK', label: 'Soirée', role: 'member', maxUses: 3, usedCount: 1, expiresAt: null, status: 'active' }
const users = {
  total: 2, page: 1, perPage: 50,
  items: [
    { id: 1, email: 'admin@example.test', role: 'member', isAdmin: true, createdAt: '2026-10-01T10:00:00+00:00', invitationLabel: null },
    { id: 2, email: 'joueur@example.test', role: 'member', isAdmin: false, emailVerified: false, createdAt: '2026-10-02T10:00:00+00:00', invitationLabel: 'Soirée' },
  ],
}

describe('AdminMembers', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getInvitations.mockResolvedValue([code])
    getAdminUsers.mockResolvedValue(users)
  })

  it('crée un code d’invitation avec son rôle et son nombre d’usages', async () => {
    createInvitation.mockResolvedValue({ ...code, id: 6, code: 'ZZZZZ-YYYYY', label: 'Pour Camille', role: 'vip', maxUses: 1, usedCount: 0 })
    render(<AdminMembers />)

    await screen.findByText('ABCDE-FGHJK')
    await userEvent.type(screen.getByLabelText('Libellé'), 'Pour Camille')
    await userEvent.selectOptions(screen.getByLabelText('Rôle accordé'), 'vip')
    await userEvent.click(screen.getByRole('button', { name: 'Créer le code' }))

    expect(createInvitation).toHaveBeenCalledWith({ label: 'Pour Camille', role: 'vip', maxUses: 1 })
    expect(await screen.findByText('ZZZZZ-YYYYY')).toBeInTheDocument()
  })

  it('change le rôle d’un compte, sauf le sien', async () => {
    updateUserRole.mockResolvedValue({ id: 2, role: 'vip' })
    render(<AdminMembers />)

    const ligne = (await screen.findByText('joueur@example.test')).closest('li')
    await userEvent.selectOptions(within(ligne).getByRole('combobox'), 'vip')

    expect(updateUserRole).toHaveBeenCalledWith(2, 'vip')
    expect(screen.getByText('Membre (vous)')).toBeInTheDocument()
  })

  it('signale les comptes dont l’adresse n’est pas confirmée', async () => {
    render(<AdminMembers />)

    const ligne = (await screen.findByText('joueur@example.test')).closest('li')

    expect(within(ligne).getByText('Non confirmé')).toBeInTheDocument()
  })

  it('filtre les comptes par rôle', async () => {
    render(<AdminMembers />)
    await screen.findByText('joueur@example.test')

    await userEvent.click(screen.getByRole('button', { name: 'VIP' }))

    expect(getAdminUsers).toHaveBeenLastCalledWith({ role: 'vip', q: '', page: 1 })
  })
})
