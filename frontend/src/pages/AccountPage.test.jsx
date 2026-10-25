import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AccountPage from './AccountPage'

const logout = vi.fn()
const login = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { email: 'a@example.test', memberNumber: null }, login, logout }) }))
vi.mock('../api', () => ({ exportAccount: vi.fn(), deleteAccount: vi.fn(), changePassword: vi.fn(), updateMemberNumber: vi.fn() }))
import { changePassword, deleteAccount, updateMemberNumber } from '../api'

const renderPage = () => render(<MemoryRouter><AccountPage /></MemoryRouter>)
const section = (name) => within(screen.getByRole('region', { name }))

describe('AccountPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.setItem('aqoj_token', 'jeton-actuel')
  })

  it('demande le mot de passe avant de supprimer, puis déconnecte', async () => {
    deleteAccount.mockResolvedValue({ deleted: true })
    renderPage()

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer mon compte' }))
    await userEvent.type(section('Supprimer mon compte').getByLabelText('Votre mot de passe, pour confirmer'), 'secret-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }))

    await waitFor(() => expect(logout).toHaveBeenCalled())
    expect(deleteAccount).toHaveBeenCalledWith('secret-solide')
  })

  it("affiche l'erreur et garde la session si le mot de passe est refusé", async () => {
    deleteAccount.mockRejectedValue(new Error('Mot de passe incorrect.'))
    renderPage()

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer mon compte' }))
    await userEvent.type(section('Supprimer mon compte').getByLabelText('Votre mot de passe, pour confirmer'), 'faux')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Mot de passe incorrect.')
    expect(logout).not.toHaveBeenCalled()
  })

  it('change le mot de passe et garde la session avec le nouveau jeton', async () => {
    changePassword.mockResolvedValue({ token: 'nouveau-jeton', user: { email: 'a@example.test' } })
    renderPage()
    const form = section('Changer mon mot de passe')

    await userEvent.type(form.getByLabelText('Mot de passe actuel'), 'ancien-mot-de-passe')
    await userEvent.type(form.getByLabelText(/^Nouveau mot de passe/), 'nouveau-mot-de-passe')
    await userEvent.type(form.getByLabelText('Confirmer le nouveau mot de passe'), 'nouveau-mot-de-passe')
    await userEvent.click(form.getByRole('button', { name: 'Changer mon mot de passe' }))

    expect(await screen.findByText(/Mot de passe modifié/)).toBeInTheDocument()
    expect(changePassword).toHaveBeenCalledWith('ancien-mot-de-passe', 'nouveau-mot-de-passe')
    expect(login).toHaveBeenCalledWith('nouveau-jeton', { email: 'a@example.test' })
  })

  it('refuse une confirmation différente sans appeler le serveur', async () => {
    renderPage()
    const form = section('Changer mon mot de passe')

    await userEvent.type(form.getByLabelText('Mot de passe actuel'), 'ancien-mot-de-passe')
    await userEvent.type(form.getByLabelText(/^Nouveau mot de passe/), 'nouveau-mot-de-passe')
    await userEvent.type(form.getByLabelText('Confirmer le nouveau mot de passe'), 'autre-chose-1234')
    await userEvent.click(form.getByRole('button', { name: 'Changer mon mot de passe' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('ne correspondent pas')
    expect(changePassword).not.toHaveBeenCalled()
  })

  it('enregistre le numéro d’adhérent', async () => {
    updateMemberNumber.mockResolvedValue({ email: 'a@example.test', memberNumber: 'EDJ-0123' })
    renderPage()

    await userEvent.type(screen.getByLabelText("Numéro d'adhérent"), 'edj 123')
    await userEvent.click(section("Mon numéro d'adhérent").getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Numéro enregistré : EDJ-0123.')).toBeInTheDocument()
    expect(updateMemberNumber).toHaveBeenCalledWith('edj 123')
    expect(login).toHaveBeenCalledWith('jeton-actuel', { email: 'a@example.test', memberNumber: 'EDJ-0123' })
  })

  it('montre le mot de passe saisi avec l’œil, puis le recache', async () => {
    renderPage()
    const champ = section('Changer mon mot de passe').getByLabelText('Mot de passe actuel')
    expect(champ).toHaveAttribute('type', 'password')

    await userEvent.click(section('Changer mon mot de passe').getAllByRole('button', { name: 'Afficher le mot de passe' })[0])
    expect(champ).toHaveAttribute('type', 'text')

    await userEvent.click(section('Changer mon mot de passe').getAllByRole('button', { name: 'Masquer le mot de passe' })[0])
    expect(champ).toHaveAttribute('type', 'password')
  })
})
