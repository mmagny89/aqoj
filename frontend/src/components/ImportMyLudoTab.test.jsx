import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportMyLudoTab from './ImportMyLudoTab'

vi.mock('../api', () => ({ analyzeMyLudo: vi.fn(), importMyLudo: vi.fn(), searchGames: vi.fn() }))
import { analyzeMyLudo, importMyLudo, searchGames } from '../api'

const game = (id, name, yearPublished, extra = {}) => ({ id, name, yearPublished, ratingBgg: 7.5, usersRated: 1200, ...extra })
const row = (index, title, extra = {}) => ({ index, id: String(index), title, subtitle: '', year: 2020, publisher: 'Éditeur', rating: null, status: 'unmatched', ...extra })

const analysis = {
  summary: { total: 4, matched: 2, ambiguous: 1, unmatched: 1 },
  rows: [
    row(0, 'Happy City', { status: 'matched', game: game(10, 'Happy City', 2021), rating: 8 }),
    row(1, 'Just One', { status: 'matched', game: game(11, 'Just One', 2018) }),
    row(2, 'Trio', { status: 'ambiguous', candidates: [game(20, 'Trio', 2021), game(21, 'Trio', 2006)] }),
    row(3, 'Lama', { status: 'unmatched' }),
  ],
}

async function upload(props = {}) {
  render(<MemoryRouter><ImportMyLudoTab {...props} /></MemoryRouter>)
  await userEvent.upload(screen.getByLabelText('Fichier CSV de MyLudo'), new File(['ID;Titre\n1;Lama'], 'collection.csv', { type: 'text/csv' }))
  await userEvent.click(screen.getByRole('button', { name: 'Analyser le fichier' }))
}

describe('ImportMyLudoTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    analyzeMyLudo.mockResolvedValue(analysis)
    searchGames.mockResolvedValue([game(30, 'Llamas Unleashed', 2019)])
  })

  it('analyse le fichier, résume et sépare ce qui est à vérifier de ce qui est reconnu', async () => {
    await upload()

    expect(await screen.findByRole('heading', { name: 'À vérifier (2)' })).toBeInTheDocument()
    expect(analyzeMyLudo).toHaveBeenCalledWith('ID;Titre\n1;Lama')
    expect(screen.getByText(/4 jeux/)).toBeInTheDocument()
    expect(screen.getByText('Reconnus (2)')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Importer 2 jeux' })).toBeEnabled()
    expect(screen.getByText('2 lignes resteront de côté.')).toBeInTheDocument()
  })

  it('départage un homonyme en choisissant un candidat', async () => {
    await upload()
    await screen.findByRole('heading', { name: 'À vérifier (2)' })

    const trio = within(screen.getByRole('heading', { name: 'Trio' }).closest('li'))
    await userEvent.click(trio.getByRole('radio', { name: /Trio \(2021\)/ }))

    expect(screen.getByRole('button', { name: 'Importer 3 jeux' })).toBeInTheDocument()

    await userEvent.click(trio.getByRole('radio', { name: 'Ne pas importer ce jeu' }))
    expect(screen.getByRole('button', { name: 'Importer 2 jeux' })).toBeInTheDocument()
  })

  it('rapproche un jeu introuvable en le cherchant dans le catalogue', async () => {
    await upload()
    await screen.findByRole('heading', { name: 'À vérifier (2)' })

    const lama = within(screen.getByRole('heading', { name: 'Lama' }).closest('li'))
    await userEvent.type(lama.getByLabelText('Chercher dans le catalogue'), 'llamas')
    await userEvent.click(await lama.findByRole('button', { name: /Llamas Unleashed/ }))

    expect(searchGames).toHaveBeenCalledWith({ q: 'llamas', local: true })
    expect(screen.getByRole('button', { name: 'Importer 3 jeux' })).toBeInTheDocument()
  })

  it('permet de décocher un jeu reconnu', async () => {
    await upload()
    await screen.findByRole('heading', { name: 'À vérifier (2)' })

    await userEvent.click(screen.getByText('Reconnus (2)'))
    await userEvent.click(screen.getByRole('checkbox', { name: /Just One/ }))

    expect(screen.getByRole('button', { name: 'Importer 1 jeu' })).toBeInTheDocument()
  })

  it('envoie les jeux confirmés avec leur note, puis affiche le bilan', async () => {
    importMyLudo.mockResolvedValue({ total: 3, added: 2, existing: 1, rated: 1 })
    await upload()
    await screen.findByRole('heading', { name: 'À vérifier (2)' })
    const trio = within(screen.getByRole('heading', { name: 'Trio' }).closest('li'))
    await userEvent.click(trio.getByRole('radio', { name: /Trio \(2021\)/ }))

    await userEvent.click(screen.getByRole('button', { name: 'Importer 3 jeux' }))

    expect(importMyLudo).toHaveBeenCalledWith([
      { gameId: 10, rating: 8 },
      { gameId: 11, rating: null },
      { gameId: 20, rating: null },
    ], 'me')
    expect(await screen.findByRole('heading', { name: 'Import terminé' })).toBeInTheDocument()
    expect(screen.getByText(/2 jeux ajoutés, 1 déjà dans votre ludothèque, 1 note reprise\. 1 ligne laissée de côté\./)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir ma ludothèque' })).toHaveAttribute('href', '/ludotheque')
  })

  it('affiche l’erreur quand le fichier n’est pas un export MyLudo', async () => {
    analyzeMyLudo.mockRejectedValue(new Error('Ce fichier ne ressemble pas à un export MyLudo.'))
    await upload()

    expect(await screen.findByRole('alert')).toHaveTextContent('ne ressemble pas à un export MyLudo')
    expect(screen.queryByRole('button', { name: /^Importer/ })).not.toBeInTheDocument()
  })

  it('importe dans la ludothèque de l’association quand un administrateur la vise', async () => {
    importMyLudo.mockResolvedValue({ total: 2, added: 2, existing: 0, rated: 0, target: 'association' })
    const onImported = vi.fn()
    await upload({ target: 'association', onImported })
    await screen.findByRole('heading', { name: 'À vérifier (2)' })

    await userEvent.click(screen.getByRole('button', { name: 'Importer 2 jeux' }))

    expect(importMyLudo).toHaveBeenCalledWith(expect.any(Array), 'association')
    expect(await screen.findByText(/2 jeux ajoutés, 0 déjà dans la ludothèque de l’association/)).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Voir ma ludothèque' })).not.toBeInTheDocument()
    expect(onImported).toHaveBeenCalled()
  })
})
