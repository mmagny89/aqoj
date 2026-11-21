import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import LandingPage from './LandingPage'

describe('LandingPage', () => {
  it('annonce que l’accès est réservé aux adhérents et propose connexion et inscription', () => {
    render(<MemoryRouter><LandingPage /></MemoryRouter>)

    expect(screen.getByRole('note')).toHaveTextContent(/réservé aux adhérents.*Envie de jouer/)
    expect(screen.getByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
    expect(screen.getByRole('link', { name: 'Créer mon compte' })).toHaveAttribute('href', '/inscription')
  })
})
