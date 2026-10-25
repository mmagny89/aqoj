import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import LoginPage from './LoginPage'
import RegisterPage from './RegisterPage'

const login = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ login }) }))
vi.mock('../api', () => ({ login: vi.fn(), register: vi.fn(), getRegistrationConfig: vi.fn() }))
import { getRegistrationConfig, login as apiLogin, register as apiRegister } from '../api'

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
    apiRegister.mockResolvedValue({ token: 't', user: { email: 'a@example.test' } })
    renderRoutes('/inscription')

    const champ = await screen.findByLabelText(/Code d'invitation/)
    await userEvent.type(champ, 'ABCDE-FGHJK')
    await userEvent.type(screen.getByLabelText('E-mail'), 'a@example.test')
    await userEvent.type(screen.getByLabelText(/^Mot de passe/), 'mot-de-passe-solide')
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'mot-de-passe-solide')
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(apiRegister).toHaveBeenCalledWith('a@example.test', 'mot-de-passe-solide', 'ABCDE-FGHJK')
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
