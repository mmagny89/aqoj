import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import Footer from './Footer'

describe('Footer', () => {
  it('renvoie vers les pages légales', () => {
    render(<MemoryRouter><Footer /></MemoryRouter>)

    expect(screen.getByRole('link', { name: 'Mentions légales' })).toHaveAttribute('href', '/mentions-legales')
    expect(screen.getByRole('link', { name: 'Confidentialité' })).toHaveAttribute('href', '/confidentialite')
  })

  it('affiche la mention BoardGameGeek avec un lien retour', () => {
    render(<MemoryRouter><Footer /></MemoryRouter>)

    const link = screen.getByRole('link', { name: /Powered by BGG/ })
    expect(link).toHaveAttribute('href', 'https://boardgamegeek.com')
    expect(link).toHaveAttribute('rel', expect.stringContaining('noopener'))
  })
})
