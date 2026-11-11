import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportPlaysTab from './ImportPlaysTab'

vi.mock('../api', () => ({ analyzeMyLudoPlays: vi.fn(), importMyLudoPlays: vi.fn(), searchGames: vi.fn() }))
import { analyzeMyLudoPlays, importMyLudoPlays, searchGames } from '../api'

const game = (id, name, yearPublished) => ({ id, name, yearPublished, ratingBgg: 7.5, usersRated: 1200 })
const row = (index, id, title, plays, extra = {}) => ({ index, id, title, subtitle: '', year: 2024, plays, already: 0, status: 'unmatched', ...extra })

const analysis = {
  summary: { plays: 9, excluded: 2, already: 3, total: 3, matched: 1, ambiguous: 1, unmatched: 1 },
  rows: [
    row(0, '501', 'Panorama', 5, { status: 'matched', game: game(10, 'Panorama', 2026) }),
    row(1, '502', 'Trio', 3, { status: 'ambiguous', candidates: [game(20, 'Trio', 2021), game(21, 'Trio', 2006)] }),
    row(2, '503', 'Lama', 1),
  ],
}

async function upload() {
  render(<MemoryRouter><ImportPlaysTab /></MemoryRouter>)
  await userEvent.upload(screen.getByLabelText('Fichier CSV des parties'), new File(['ID;Titre;Date;Joueurs\n1;Lama;2026-10-25;"Moi (1)"'], 'plays.csv', { type: 'text/csv' }))
  await userEvent.click(screen.getByRole('button', { name: 'Analyser le fichier' }))
}

describe('ImportPlaysTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    analyzeMyLudoPlays.mockResolvedValue(analysis)
    searchGames.mockResolvedValue([])
  })

  it('résume les parties, signale les doublons et les exclues, et compte ce qui sera importé', async () => {
    await upload()

    expect(await screen.findByRole('heading', { name: 'À vérifier (2)' })).toBeInTheDocument()
    expect(screen.getByText(/9/, { selector: 'strong' })).toBeInTheDocument()
    expect(screen.getByText(/3 parties déjà importées/)).toBeInTheDocument()
    expect(screen.getByText(/2 parties marquées « exclues » dans MyLudo/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Importer 5 parties de 1 jeu' })).toBeEnabled()
    expect(screen.getByText('4 parties resteront de côté (jeux non confirmés).')).toBeInTheDocument()
  })

  it('départage un homonyme puis envoie le fichier avec la correspondance jeu MyLudo → jeu du catalogue', async () => {
    importMyLudoPlays.mockResolvedValue({ imported: 8, duplicates: 0, left: 1, excluded: 2, invalid: 0 })
    await upload()

    await userEvent.click(await screen.findByRole('radio', { name: /Trio \(2021\)/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Importer 8 parties de 2 jeux' }))

    expect(importMyLudoPlays).toHaveBeenCalledWith(expect.stringContaining('Lama'), { 501: 10, 502: 20 })
    expect(await screen.findByRole('heading', { name: 'Import terminé' })).toBeInTheDocument()
    expect(screen.getByText(/8 parties ajoutées/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir mes parties' })).toHaveAttribute('href', '/parties')
  })

  it('affiche l’erreur d’un fichier qui n’est pas un export de parties', async () => {
    analyzeMyLudoPlays.mockRejectedValue(new Error('Ce fichier ne ressemble pas à un export de parties MyLudo : la colonne « Date » manque.'))
    await upload()

    expect(await screen.findByText(/la colonne « Date » manque/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Importer/ })).not.toBeInTheDocument()
  })
})
