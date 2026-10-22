import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'

vi.mock('../context/AuthContext', () => ({ useAuth: vi.fn() }))
import { useAuth } from '../context/AuthContext'

function renderAt(auth) {
  useAuth.mockReturnValue(auth)

  return render(
    <MemoryRouter initialEntries={['/ludotheque']}>
      <Routes>
        <Route path="/connexion" element={<p>Page de connexion</p>} />
        <Route path="/ludotheque" element={<ProtectedRoute><p>Ludothèque</p></ProtectedRoute>} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('ProtectedRoute', () => {
  it('affiche la page à un utilisateur connecté', () => {
    renderAt({ user: { email: 'a@example.test' }, loading: false })

    expect(screen.getByText('Ludothèque')).toBeInTheDocument()
  })

  it('renvoie un visiteur vers la connexion', () => {
    renderAt({ user: null, loading: false })

    expect(screen.getByText('Page de connexion')).toBeInTheDocument()
  })

  it('ne redirige pas tant que la session est en cours de vérification', () => {
    renderAt({ user: null, loading: true })

    expect(screen.queryByText('Page de connexion')).not.toBeInTheDocument()
    expect(screen.queryByText('Ludothèque')).not.toBeInTheDocument()
  })
})
