import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import HelpPage from './HelpPage'
import LegalNoticePage from './LegalNoticePage'

describe('Aide et mentions légales', () => {
  it('structure l’aide en sections titrées et renvoie vers les actions principales', () => {
    render(<MemoryRouter><HelpPage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('À quoi on joue ?')
    expect(screen.getByRole('heading', { name: 'Comment marchent les recommandations ?' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Trouver un jeu pour ce soir' })).toHaveAttribute('href', '/recommander')
    expect(screen.getByRole('link', { name: 'Importer ma collection' })).toHaveAttribute('href', '/importer')
  })

  it('affiche les mentions légales avec leur date de mise à jour', () => {
    render(<MemoryRouter><LegalNoticePage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1, name: 'Mentions légales' })).toBeInTheDocument()
    expect(screen.getByText(/Dernière mise à jour/)).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Hébergement' })).toBeInTheDocument()
  })
})
