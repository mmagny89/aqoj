import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportPage from './ImportPage'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { email: 'a@example.test' }, login: vi.fn() }) }))
vi.mock('../api', () => ({ importBgg: vi.fn(), getMe: vi.fn() }))

const renderAt = (url = '/importer') => render(<MemoryRouter initialEntries={[url]}><ImportPage /></MemoryRouter>)

describe('ImportPage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('ouvre l’onglet du compte BGG par défaut, avec trois onglets', () => {
    renderAt()

    expect(screen.getByRole('heading', { level: 1, name: 'Import' })).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map(t => t.textContent)).toEqual(['Compte BGG', 'Ma collection MyLudo', 'Mes parties MyLudo'])
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByLabelText('Pseudo BoardGameGeek')).toBeInTheDocument()
  })

  it('change d’onglet au clic et au clavier', async () => {
    renderAt()

    await userEvent.click(screen.getByRole('tab', { name: 'Ma collection MyLudo' }))
    expect(screen.getByRole('tab', { name: 'Ma collection MyLudo' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByLabelText('Pseudo BoardGameGeek')).not.toBeInTheDocument()

    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Mes parties MyLudo' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Mes parties MyLudo' })).toHaveFocus()

    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
  })

  it('ouvre l’onglet demandé par l’adresse et retombe sur BGG si elle est inconnue', () => {
    const { unmount } = renderAt('/importer?onglet=myludo')
    expect(screen.getByRole('tab', { name: 'Ma collection MyLudo' })).toHaveAttribute('aria-selected', 'true')
    unmount()

    renderAt('/importer?onglet=nimporte-quoi')
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
  })
})
