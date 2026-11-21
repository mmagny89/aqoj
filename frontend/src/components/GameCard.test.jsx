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

  it('dit pourquoi le jeu est proposé et offre les deux retours', async () => {
    const onFeedback = vi.fn()
    renderCard({ why: { liked: null, families: ['auction', 'cooperation'] }, onFeedback })

    // Discret : replié tant qu'on ne le demande pas.
    expect(screen.getByTestId('why')).not.toHaveAttribute('open')
    await userEvent.click(screen.getByText('Pourquoi ce jeu ?'))
    expect(screen.getByTestId('why')).toHaveAttribute('open')
    expect(screen.getByTestId('why')).toHaveTextContent(/Pourquoi ce jeu \?Vous aimez les mécaniques\s*:/)
    expect(screen.getByRole('list', { name: '' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /Déjà joué, bof/ }))
    expect(onFeedback).toHaveBeenCalledWith(game, 'played_meh', null)
  })

  it('propose quatre retours en icônes, avec une infobulle, et retire celui qui est enfoncé', async () => {
    const onFeedback = vi.fn()
    renderCard({ verdict: 'want_to_play', onFeedback })

    expect(screen.getAllByRole('button')).toHaveLength(4)
    const envie = screen.getByRole('button', { name: /J’aimerais jouer/ })
    expect(envie).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: /J’ai joué, et j’aime/ })).toHaveAttribute('aria-pressed', 'false')

    await userEvent.hover(screen.getByRole('button', { name: /Pas intéressé/ }))
    expect(screen.getByRole('tooltip')).toHaveTextContent('Ne plus me proposer ce jeu.')
    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument()

    await userEvent.click(envie)
    expect(onFeedback).toHaveBeenCalledWith(game, null, 'want_to_play')
  })

  it('n’affiche ni explication ni retour sans données', () => {
    renderCard()
    expect(screen.queryByTestId('why')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Pas intéressé/ })).not.toBeInTheDocument()
  })

  it('affiche le tag de sortie fourni par l’API', () => {
    renderCard()
    expect(screen.queryByText('Nouveauté')).not.toBeInTheDocument()
  })

  it.each([['new', 'Nouveauté'], ['upcoming', 'À paraître']])('affiche « %s » comme étiquette', (release, texte) => {
    render(<MemoryRouter><GameCard game={{ ...game, release }} /></MemoryRouter>)
    expect(screen.getByText(texte)).toBeInTheDocument()
  })

  it('garde le bouton d’action indépendant du lien', async () => {
    const onAddSession = vi.fn()
    renderCard({ onAddSession })

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer une partie' }))

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
