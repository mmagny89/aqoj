import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import AppShell from './AppShell'

let currentUser = null
const logout = vi.fn()
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: currentUser, logout }) }))

function Where() {
  const location = useLocation()
  return <p>route : {location.pathname}{location.search}</p>
}

const renderShell = () => render(
  <MemoryRouter initialEntries={['/']}>
    <AppShell><Routes><Route path="*" element={<Where />} /></Routes></AppShell>
  </MemoryRouter>,
)

describe('AppShell', () => {
  beforeEach(() => {
    currentUser = null
    logout.mockClear()
  })

  it('propose la navigation principale au bureau et sur téléphone', () => {
    renderShell()

    const navigations = screen.getAllByRole('navigation', { name: 'Navigation principale' })
    expect(navigations).toHaveLength(2)
    expect(within(navigations[0]).getByRole('link', { name: 'À quoi on joue' })).toHaveAttribute('href', '/recommander')
    expect(within(navigations[1]).getByRole('link', { name: 'Connexion' })).toHaveAttribute('href', '/connexion')
  })

  it('n’affiche l’administration qu’aux administrateurs', () => {
    renderShell()
    expect(screen.queryByRole('link', { name: 'Administration' })).not.toBeInTheDocument()
  })

  it('montre l’administration et le statut Admin, et se déconnecte', async () => {
    currentUser = { email: 'asso@example.test', isAdmin: true }
    renderShell()

    expect(screen.getByRole('link', { name: 'Administration' })).toHaveAttribute('href', '/admin')
    expect(screen.getByText('Admin')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /Se déconnecter/ }))
    expect(logout).toHaveBeenCalled()
  })

  it('lance la recherche depuis la barre du haut', async () => {
    renderShell()

    await userEvent.type(screen.getByRole('searchbox', { name: 'Chercher un jeu' }), 'catan{Enter}')

    expect(screen.getByText('route : /rechercher?q=catan')).toBeInTheDocument()
  })

  it('offre un lien d’évitement vers le contenu', () => {
    renderShell()
    expect(screen.getByRole('link', { name: 'Aller au contenu' })).toHaveAttribute('href', '#contenu')
  })
})
