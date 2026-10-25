import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import LoginPage from './LoginPage'
import RegisterPage from './RegisterPage'

const login = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ login }) }))
vi.mock('../api', () => ({ login: vi.fn(), register: vi.fn(), getRegistrationConfig: vi.fn(), resendVerification: vi.fn() }))
import { getRegistrationConfig, login as apiLogin, register as apiRegister, resendVerification } from '../api'

const renderRoutes = (url) => render(
  <MemoryRouter initialEntries={[url]}>
    <Routes>
      <Route path="/" element={<p>accueil</p>} />
      <Route path="/connexion" element={<LoginPage />} />
      <Route path="/inscription" element={<RegisterPage />} />
    </Routes>
  </MemoryRouter>,
)

describe('Connexion et inscription', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getRegistrationConfig.mockResolvedValue({ requiresCode: false })
  })

  it('connecte puis ramène à l’accueil', async () => {
    apiLogin.mockResolvedValue({ token: 't', user: { email: 'a@example.test' } })
    renderRoutes('/connexion')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText('Mot de passe'), 'secret-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByText('accueil')).toBeInTheDocument()
    expect(login).toHaveBeenCalledWith('t', { email: 'a@example.test' })
  })

  it('propose le lien Mot de passe oublié', () => {
    renderRoutes('/connexion')

    expect(screen.getByRole('link', { name: 'Mot de passe oublié ?' })).toHaveAttribute('href', '/mot-de-passe-oublie')
  })

  it('montre l’erreur d’identifiants', async () => {
    apiLogin.mockRejectedValue(new Error('Identifiants invalides.'))
    renderRoutes('/connexion')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText('Mot de passe'), 'faux-mot-de-passe')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Identifiants invalides.')
  })

  it('réclame le code d’invitation quand l’inscription l’exige et l’envoie', async () => {
    getRegistrationConfig.mockResolvedValue({ requiresCode: true })
    apiRegister.mockResolvedValue({ verificationRequired: true })
    renderRoutes('/inscription')

    const champ = await screen.findByLabelText(/Code d'invitation/)
    await userEvent.type(champ, 'ABCDE-FGHJK')
    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText(/^Mot de passe/), 'mot-de-passe-solide')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'mot-de-passe-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(apiRegister).toHaveBeenCalledWith('a@example.test', 'mot-de-passe-solide', 'ABCDE-FGHJK')
    // Pas de session ouverte : l'adresse reste à confirmer.
    expect(await screen.findByRole('heading', { name: 'Vérifiez vos e-mails' })).toBeInTheDocument()
    expect(login).not.toHaveBeenCalled()
  })

  it('permet de renvoyer l’e-mail de confirmation après l’inscription', async () => {
    apiRegister.mockResolvedValue({ verificationRequired: true })
    resendVerification.mockResolvedValue({})
    renderRoutes('/inscription')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText(/^Mot de passe/), 'mot-de-passe-solide')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'mot-de-passe-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))
    await userEvent.click(await screen.findByRole('button', { name: "Renvoyer l'e-mail de confirmation" }))

    expect(resendVerification).toHaveBeenCalledWith('a@example.test')
    expect(await screen.findByText(/Un nouvel e-mail vient de partir/)).toBeInTheDocument()
  })

  it('propose de renvoyer le lien quand l’adresse n’est pas confirmée', async () => {
    const erreur = new Error('Adresse e-mail non confirmée. Ouvrez le lien reçu par e-mail, ou demandez-en un nouveau.')
    erreur.code = 'email_not_verified'
    apiLogin.mockRejectedValue(erreur)
    resendVerification.mockResolvedValue({})
    renderRoutes('/connexion')

    await userEvent.type(screen.getByLabelText('E-mail'), 'attente@example.test')
    await userEvent.type(screen.getByLabelText('Mot de passe'), 'secret-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))
    await userEvent.click(await screen.findByRole('button', { name: "Renvoyer l'e-mail de confirmation" }))

    expect(resendVerification).toHaveBeenCalledWith('attente@example.test')
  })

  it('ne propose pas le renvoi pour un simple mot de passe faux', async () => {
    apiLogin.mockRejectedValue(new Error('E-mail ou mot de passe incorrect.'))
    renderRoutes('/connexion')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText('Mot de passe'), 'faux-mot-de-passe')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: "Renvoyer l'e-mail de confirmation" })).not.toBeInTheDocument()
  })

  it('refuse une confirmation différente avant tout appel', async () => {
    renderRoutes('/inscription')

    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText(/^Mot de passe/), 'mot-de-passe-solide')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'autre-chose-1234')
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Les mots de passe ne correspondent pas.')
    expect(apiRegister).not.toHaveBeenCalled()
  })
})
