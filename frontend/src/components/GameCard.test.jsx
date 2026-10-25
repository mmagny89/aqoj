import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import GameCard, { initials, weightDots } from './GameCard'

const game = { id: 616, name: 'Catan', minPlayers: 3, maxPlayers: 4, playingTime: 90, complexity: 2.3, ratingBgg: 7.1, yearPublished: 1995 }
const renderCard = (props) => render(<MemoryRouter><GameCard game={game} {...props} /></MemoryRouter>)

describe('GameCard', () => {
  it('est un vrai lien vers la fiche, avec les faits clés', () => {
    renderCard()

    expect(screen.getByRole('link', { name: 'Catan' })).toHaveAttribute('href', '/games/616')
    expect(screen.getByText('3–4 joueurs')).toBeInTheDocument()
    expect(screen.getByText('90 min')).toBeInTheDocument()
    expect(screen.getByText('★ 7.1')).toBeInTheDocument()
  })

  it('garde le bouton d’action indépendant du lien', async () => {
    const onAddSession = vi.fn()
    renderCard({ onAddSession })

    await userEvent.click(screen.getByRole('button', { name: '+ Enregistrer une partie' }))

    expect(onAddSession).toHaveBeenCalledWith(game)
  })

  it('montre la boîte de l’édition française quand elle existe', () => {
    const { container } = render(<MemoryRouter><GameCard game={{ ...game, imageUrl: 'https://cf.geekdo-images.com/origine.jpg', editionFrImageUrl: 'https://cf.geekdo-images.com/boite-fr.jpg' }} /></MemoryRouter>)

    expect(container.querySelector('img')).toHaveAttribute('src', 'https://cf.geekdo-images.com/boite-fr.jpg')
  })

  it('remplace l’image absente par les initiales du jeu', () => {
    expect(initials('Brass: Birmingham')).toBe('BB')
    expect(initials('Catan')).toBe('Ca')
    expect(weightDots(2.3)).toBe('●●○○○')
  })
})
