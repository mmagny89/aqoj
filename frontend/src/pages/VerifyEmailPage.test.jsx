import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import VerifyEmailPage from './VerifyEmailPage'

const login = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ login }) }))
vi.mock('../api', () => ({ verifyEmail: vi.fn() }))
import { verifyEmail } from '../api'

const renderAt = (url) => render(
  <MemoryRouter initialEntries={[url]}>
    <Routes>
      <Route path="/" element={<p>accueil</p>} />
      <Route path="/verifier-adresse" element={<VerifyEmailPage />} />
    </Routes>
  </MemoryRouter>,
)

describe('VerifyEmailPage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('confirme l’adresse avec le jeton du lien, ouvre la session et renvoie à l’accueil', async () => {
    verifyEmail.mockResolvedValue({ token: 'session', user: { email: 'a@example.test' } })
    renderAt('/verifier-adresse?jeton=abc123')

    expect(await screen.findByText('accueil')).toBeInTheDocument()
    expect(verifyEmail).toHaveBeenCalledTimes(1)
    expect(verifyEmail).toHaveBeenCalledWith('abc123')
    expect(login).toHaveBeenCalledWith('session', { email: 'a@example.test' })
  })

  it('explique un lien expiré et renvoie vers la connexion', async () => {
    verifyEmail.mockRejectedValue(new Error('Lien invalide ou expiré. Demandez-en un nouveau.'))
    renderAt('/verifier-adresse?jeton=perime')

    expect(await screen.findByRole('alert')).toHaveTextContent('Lien invalide ou expiré')
    expect(screen.getByRole('link', { name: 'Aller à la connexion' })).toHaveAttribute('href', '/connexion')
    expect(login).not.toHaveBeenCalled()
  })

  it('refuse un lien sans jeton sans appeler le serveur', () => {
    renderAt('/verifier-adresse')

    expect(screen.getByRole('heading', { name: 'Lien incomplet' })).toBeInTheDocument()
    expect(verifyEmail).not.toHaveBeenCalled()
  })
})
