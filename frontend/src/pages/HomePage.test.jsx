import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import HomePage from './HomePage'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: null, loading: false }) }))
vi.mock('../api', () => ({ getHomeRecommendations: vi.fn(), getForgottenGems: vi.fn() }))

function Where() {
  const location = useLocation()
  return <p>route : {location.pathname}{location.search}</p>
}

describe('HomePage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('envoie la table et la durée choisies à la page de recommandation', async () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/recommander" element={<Where />} />
        </Routes>
      </MemoryRouter>,
    )

    await userEvent.click(screen.getByRole('button', { name: '2' }))
    await userEvent.click(screen.getByRole('button', { name: '≤ 2 h' }))
    await userEvent.click(screen.getByRole('button', { name: 'Trouver un jeu' }))

    expect(screen.getByText('route : /recommander?joueurs=2&duree=120')).toBeInTheDocument()
  })

  it('propose les entrées principales quand on n’est pas connecté', () => {
    render(<MemoryRouter><HomePage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent("Qu'est-ce qu'on joue ce soir")
    expect(screen.getByRole('link', { name: /Ma ludothèque/ })).toHaveAttribute('href', '/ludotheque')
  })
})
