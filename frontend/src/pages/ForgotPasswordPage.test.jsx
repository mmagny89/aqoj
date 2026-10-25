import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import ForgotPasswordPage from './ForgotPasswordPage'
import ResetPasswordPage from './ResetPasswordPage'

vi.mock('../api', () => ({ forgotPassword: vi.fn(), resetPassword: vi.fn() }))
import { forgotPassword, resetPassword } from '../api'

const renderAt = (url) => render(
  <MemoryRouter initialEntries={[url]}>
    <Routes>
      <Route path="/mot-de-passe-oublie" element={<ForgotPasswordPage />} />
      <Route path="/reinitialiser-mot-de-passe" element={<ResetPasswordPage />} />
    </Routes>
  </MemoryRouter>,
)

describe('Mot de passe oublié et réinitialisation', () => {
  beforeEach(() => vi.clearAllMocks())

  it('annonce l’envoi sans dire si le compte existe', async () => {
    forgotPassword.mockResolvedValue({ message: 'ok' })
    renderAt('/mot-de-passe-oublie')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.click(screen.getByRole('button', { name: 'Envoyer le lien' }))

    expect(await screen.findByRole('status')).toHaveTextContent('Si un compte existe avec cette adresse')
    expect(forgotPassword).toHaveBeenCalledWith('a@example.test')
  })

  it('choisit un nouveau mot de passe avec le jeton du lien', async () => {
    resetPassword.mockResolvedValue({})
    renderAt('/reinitialiser-mot-de-passe?jeton=abc123')

    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'nouveau-mot-de-passe')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'nouveau-mot-de-passe')
    await userEvent.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }))

    expect(resetPassword).toHaveBeenCalledWith('abc123', 'nouveau-mot-de-passe')
    expect(await screen.findByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
  })

  it('affiche l’erreur d’un lien expiré et propose d’en demander un autre', async () => {
    resetPassword.mockRejectedValue(new Error('Lien invalide ou expiré. Demandez-en un nouveau.'))
    renderAt('/reinitialiser-mot-de-passe?jeton=perime')

    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'nouveau-mot-de-passe')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'nouveau-mot-de-passe')
    await userEvent.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Lien invalide ou expiré')
    expect(screen.getByRole('link', { name: 'Demander un nouveau lien' })).toHaveAttribute('href', '/mot-de-passe-oublie')
  })

  it('refuse un lien sans jeton', () => {
    renderAt('/reinitialiser-mot-de-passe')

    expect(screen.getByRole('heading', { name: 'Lien incomplet' })).toBeInTheDocument()
  })
})
