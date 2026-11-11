import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { getForgottenGems, getHomeRecommendations, getTonightRecommendations, saveTable } from '../api'
import GameCard from '../components/GameCard'
import FilterRow from '../components/FilterRow'
import { DiceIcon, DownloadIcon, SearchIcon, ShelfIcon } from '../components/icons'

const PLAYERS = [1, 2, 3, 4, 5, 6, 7, 8]
const DURATIONS = [
  { label: '≤ 15 min', value: 15 },
  { label: '≤ 30 min', value: 30 },
  { label: '≤ 1 h', value: 60 },
  { label: '≤ 1 h 30', value: 90 },
  { label: '≤ 2 h', value: 120 },
  { label: '≤ 3 h', value: 180 },
  { label: 'Peu importe', value: 240 },
]
const DEFAULT_TABLE = { players: 4, maxTime: 60 }

/* ── Bandeau « Qu'est-ce qu'on joue ce soir ? » ──────────────────────── */
function Hero({ table, onTableChange, saved, scopeSwitch }) {
  const { players, maxTime } = table

  return (
    <section aria-labelledby="titre-hero" className="panel px-4 py-4 sm:px-6 sm:py-5">
      <div className="flex min-w-0 flex-col gap-2.5">
        <h1 id="titre-hero" className="text-2xl leading-tight sm:text-3xl">Qu'est-ce qu'on joue ce soir&nbsp;?</h1>

        <FilterRow id="filtre-joueurs" label="Joueurs">
          {PLAYERS.map(n => (
            <button key={n} type="button" className="chip chip-sm min-w-8 justify-center" aria-pressed={players === n} onClick={() => onTableChange({ ...table, players: n })}>
              {n}
            </button>
          ))}
        </FilterRow>

        <FilterRow id="filtre-duree" label="Durée">
          {DURATIONS.map(({ label, value }) => (
            <button key={value} type="button" className="chip chip-sm" aria-pressed={maxTime === value} onClick={() => onTableChange({ ...table, maxTime: value })}>
              {label}
            </button>
          ))}
        </FilterRow>

        {scopeSwitch}

        <p role="status" className="m-0 text-sm text-stone-600 empty:hidden">
          {saved === 'ok' && 'Table enregistrée.'}
          {saved === 'error' && 'Impossible d’enregistrer la table pour l’instant.'}
        </p>
      </div>
    </section>
  )
}

function SectionTitle({ id, children, action }) {
  return (
    <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
      <h2 id={id} className="text-2xl sm:text-3xl">{children}</h2>
      {action}
    </div>
  )
}

/* ── Carrousel horizontal ────────────────────────────────────────────── */
function Carousel({ items, label }) {
  const ref = useRef(null)
  const scroll = (dir) => ref.current?.scrollBy({ left: dir * 300, behavior: 'smooth' })

  return (
    <div className="relative">
      <button type="button" onClick={() => scroll(-1)} aria-label={`${label} : précédents`}
        className="btn absolute -left-3 top-1/3 z-10 hidden !min-h-11 w-11 !px-0 bg-paper shadow-token-sm lg:flex">‹</button>
      <ul ref={ref} aria-label={label} className="m-0 flex list-none snap-x snap-mandatory gap-5 overflow-x-auto px-1 pb-4 pt-1" style={{ scrollbarWidth: 'thin' }}>
        {items.map(({ game, reason }) => (
          <li key={game.id} className="w-48 shrink-0 snap-start sm:w-56">
            <GameCard game={game} reason={reason} />
          </li>
        ))}
      </ul>
      <button type="button" onClick={() => scroll(1)} aria-label={`${label} : suivants`}
        className="btn absolute -right-3 top-1/3 z-10 hidden !min-h-11 w-11 !px-0 bg-paper shadow-token-sm lg:flex">›</button>
    </div>
  )
}

/* ── Pour la table du soir ───────────────────────────────────────────── */
function TonightSection({ tonight, players, maxTime }) {
  const durationLabel = maxTime < 240 ? DURATIONS.find(d => d.value === maxTime)?.label : 'Durée libre'

  return (
    <section aria-labelledby="titre-table" className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
        <h2 id="titre-table" className="text-2xl sm:text-3xl">Pour votre table</h2>
        <ul aria-label="Table choisie" className="m-0 flex list-none flex-wrap gap-2 p-0">
          <li className="badge bg-brand-yellow">{players} joueur{players > 1 ? 's' : ''}</li>
          <li className="badge bg-brand-yellow">{durationLabel}</li>
        </ul>
      </div>
      {tonight.popular.length > 0 && (
        <div>
          <h3 className="mb-3 text-xl sm:text-2xl">Ce que jouent les autres membres</h3>
          <Carousel items={tonight.popular} label="Ce que jouent les autres membres" />
        </div>
      )}
      {tonight.easy.length > 0 && (
        <div>
          <h3 className="mb-3 text-xl sm:text-2xl">Faciles à expliquer</h3>
          <Carousel items={tonight.easy} label="Faciles à expliquer" />
        </div>
      )}
    </section>
  )
}

// Rappel discret pour qui n'a rien déclaré (profil déduit ou démarrage à froid) : le questionnaire vit dans le compte : « Plus tard » le masque une semaine.
const INVITE_KEY = 'aqoj_gouts_plus_tard'
const INVITE_DELAY = 7 * 24 * 3600 * 1000

function inviteSnoozed() {
  try {
    return Date.now() - Number(localStorage.getItem(INVITE_KEY) ?? 0) < INVITE_DELAY
  } catch {
    return false
  }
}

function TasteInvite() {
  const [hidden, setHidden] = useState(inviteSnoozed)
  if (hidden) return null

  const later = () => {
    try { localStorage.setItem(INVITE_KEY, String(Date.now())) } catch { /* stockage indisponible : le rappel reviendra */ }
    setHidden(true)
  }

  return (
    <section aria-labelledby="titre-invitation" className="panel flex flex-col gap-3 bg-amber-100 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 id="titre-invitation" className="text-xl sm:text-2xl">Dites-nous ce que vous aimez</h2>
        <p className="mt-1 text-stone-700">Quelques styles de jeu suffisent pour des suggestions plus justes.</p>
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <Link to="/compte#gouts" className="btn btn-primary">Choisir mes goûts</Link>
        <button type="button" onClick={later} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Plus tard</button>
      </div>
    </section>
  )
}

/* ── Périmètre des résultats ─────────────────────────────────────────── */
const SCOPES = [
  { value: 'library', label: 'Ludothèque de l’asso et des membres' },
  { value: 'catalogue', label: 'Tout le catalogue' },
]
const SCOPE_KEY = 'aqoj_perimetre'

function storedScope() {
  try {
    return localStorage.getItem(SCOPE_KEY) === 'catalogue' ? 'catalogue' : 'library'
  } catch {
    return 'library'
  }
}

function ScopeSwitch({ scope, onChange }) {
  return (
    <FilterRow id="filtre-perimetre" label="Parmi">
      {SCOPES.map(({ value, label }) => (
        <button key={value} type="button" className="chip chip-sm" aria-pressed={scope === value} onClick={() => onChange(value)}>
          {label}
        </button>
      ))}
    </FilterRow>
  )
}

/* ── Onglets décennie ────────────────────────────────────────────────── */
function DecadeTabs({ byDecade }) {
  const labels = Object.keys(byDecade)
  const [active, setActive] = useState(labels[0])

  if (labels.length === 0) return null
  const games = byDecade[active] ?? []

  return (
    <div>
      <div role="tablist" aria-label="Décennies" className="mb-4 flex gap-1.5 overflow-x-auto pb-1">
        {labels.map(label => (
          <button key={label} type="button" role="tab" id={`epoque-${label}`} aria-selected={active === label} aria-controls="epoque-panneau"
            onClick={() => setActive(label)} className="chip chip-sm shrink-0">
            {label}
            <span className="ml-1.5 opacity-80">{byDecade[label].length}</span>
          </button>
        ))}
      </div>
      <ul id="epoque-panneau" role="tabpanel" aria-labelledby={`epoque-${active}`} className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 sm:gap-5 xl:grid-cols-4 2xl:grid-cols-5">
        {games.map(({ game, reason }) => (
          <li key={game.id}><GameCard game={game} reason={reason} /></li>
        ))}
      </ul>
    </div>
  )
}

/* ── Onglets de la page : nouveautés, oubliés, époques ───────────────── */
function DiscoverTabs({ tabs }) {
  const [active, setActive] = useState(tabs[0].id)
  const current = tabs.find(t => t.id === active) ?? tabs[0]

  return (
    <section aria-labelledby="titre-decouvrir">
      <h2 id="titre-decouvrir" className="sr-only">À découvrir</h2>
      <div role="tablist" aria-label="À découvrir" className="tab-list mb-5">
        {tabs.map(({ id, label }) => (
          <button key={id} type="button" role="tab" id={`onglet-${id}`} aria-selected={current.id === id} aria-controls="onglet-panneau"
            onClick={() => setActive(id)} className="tab">
            {label}
          </button>
        ))}
      </div>
      <div id="onglet-panneau" role="tabpanel" aria-labelledby={`onglet-${current.id}`}>
        {current.content}
      </div>
    </section>
  )
}

const ENTRIES = [
  { to: '/rechercher', label: 'Chercher un jeu', text: 'Par son nom, en français ou en anglais.', Icon: SearchIcon },
  { to: '/recommander', label: 'À quoi on joue', text: 'Des idées selon la table et le temps.', Icon: DiceIcon },
  { to: '/ludotheque', label: 'Ma ludothèque', text: 'Votre collection, jeu par jeu.', Icon: ShelfIcon },
  { to: '/importer', label: 'Importer ma collection', text: 'Depuis BGG ou MyLudo, en un clic.', Icon: DownloadIcon },
]

/* ── Page ────────────────────────────────────────────────────────────── */
export default function HomePage() {
  const { user, loading: authLoading } = useAuth()

  const [reco, setReco]               = useState(null)
  const [forgotten, setForgotten]     = useState([])
  const [recoLoading, setRecoLoading] = useState(false)
  const [recoError, setRecoError]     = useState(null)
  const [scope, setScope]             = useState(storedScope)

  // La table du bandeau pilote les idées « pour votre table » ; « Peu importe » (4 h) ne filtre pas la durée.
  // Elle est mémorisée sur le compte : les idées ne se chargent qu'une fois la table retrouvée.
  const [table, setTable]           = useState(DEFAULT_TABLE)
  const [tableReady, setTableReady] = useState(false)
  const [tableSaved, setTableSaved] = useState(null)
  const [tonight, setTonight]       = useState({ easy: [], popular: [] })
  const { players, maxTime } = table

  const changeScope = (next) => {
    setScope(next)
    try { localStorage.setItem(SCOPE_KEY, next) } catch { /* stockage indisponible : le choix ne sera pas retenu */ }
  }

  const changeTable = (next) => {
    setTable(next)
    setTableSaved(null)
    if (!user) return
    saveTable(next).then(() => setTableSaved('ok')).catch(() => setTableSaved('error'))
  }

  useEffect(() => {
    if (authLoading || !user) return

    setRecoLoading(true)
    setRecoError(null)
    getHomeRecommendations({ scope })
      .then(data => {
        setReco(data)
        if (data.table) setTable(data.table)
      })
      .catch(err  => setRecoError(err.message))
      .finally(() => { setRecoLoading(false); setTableReady(true) })
  }, [user, authLoading, scope])

  // Les « oubliés » sont ceux de la ludothèque commune : sans objet quand on cherche dans tout le catalogue.
  useEffect(() => {
    if (authLoading || !user) return
    if (scope !== 'library') {
      setForgotten([])
      return
    }
    getForgottenGems({ scope }).then(setForgotten).catch(() => setForgotten([]))
  }, [user, authLoading, scope])

  useEffect(() => {
    if (authLoading || !user || !tableReady) return
    let current = true
    getTonightRecommendations({ players, maxTime: maxTime >= 240 ? null : maxTime, scope })
      .then(data => { if (current) setTonight(data) })
      .catch(() => { if (current) setTonight({ easy: [], popular: [] }) })
    return () => { current = false }
  }, [user, authLoading, tableReady, players, maxTime, scope])

  // Un même jeu ne s'affiche qu'une fois : ce que jouent les membres d'abord, les nouveautés avant les « faciles ».
  const shown      = new Set(tonight.popular.map(({ game }) => game.id))
  const recentList = (reco?.recent ?? []).filter(({ game }) => !shown.has(game.id))
  recentList.forEach(({ game }) => shown.add(game.id))
  const tonightShown = { popular: tonight.popular, easy: tonight.easy.filter(({ game }) => !shown.has(game.id)) }

  const hasTonight   = tonightShown.easy.length > 0 || tonightShown.popular.length > 0
  const hasRecent    = recentList.length > 0
  const hasDecades   = reco?.byDecade && Object.keys(reco.byDecade).length > 0
  const hasForgotten = forgotten.length > 0
  const showInvite   = !recoLoading && reco !== null && !(reco.declared?.length > 0)

  const tabs = [
    hasRecent && {
      id: 'nouveautes',
      label: 'Nouveautés pour vous',
      content: (
        <>
          <p className="mb-4 mt-0 text-stone-600">Dix nouveautés parmi les plus suivies sur BGG, sorties récemment ou à paraître, qui ne sont pas dans votre ludothèque.</p>
          <Carousel items={recentList} label="Nouveautés pour vous" />
        </>
      ),
    },
    hasForgotten && {
      id: 'oublies',
      label: 'Oubliés de la ludothèque',
      content: (
        <>
          <div className="mb-4 flex justify-end">
            <Link to="/ludotheque" className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Ma ludothèque</Link>
          </div>
          <Carousel items={forgotten} label="Oubliés de la ludothèque" />
        </>
      ),
    },
    hasDecades && { id: 'epoque', label: 'Par époque', content: <DecadeTabs byDecade={reco.byDecade} /> },
  ].filter(Boolean)

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <Hero table={table} onTableChange={changeTable} saved={tableSaved}
        scopeSwitch={!authLoading && user ? <ScopeSwitch scope={scope} onChange={changeScope} /> : null} />

      {!authLoading && user && hasTonight && <TonightSection tonight={tonightShown} players={players} maxTime={maxTime} />}

      {/* ── Non connecté ───────────────────────────────────────────── */}
      {!authLoading && !user && (
        <section aria-label="Par où commencer">
          <ul className="m-0 grid list-none grid-cols-1 gap-5 p-0 sm:grid-cols-2 xl:grid-cols-4">
            {ENTRIES.map(({ to, label, text, Icon }) => (
              <li key={to}>
                <Link to={to} className="token-card h-full gap-2 p-5">
                  <Icon width={32} height={32} />
                  <span className="font-display text-2xl leading-tight">{label}</span>
                  <span className="text-stone-600">{text}</span>
                </Link>
              </li>
            ))}
          </ul>
          <p className="mt-6 text-center">
            <Link to="/connexion" className="btn btn-secondary">Se connecter pour des suggestions sur mesure</Link>
          </p>
        </section>
      )}

      {/* ── Connecté ───────────────────────────────────────────────── */}
      {!authLoading && user && (
        <>
          {recoLoading && (
            <p role="status" className="flex items-center justify-center gap-3 py-12 text-stone-600">
              <span className="h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
              Analyse de votre profil…
            </p>
          )}

          {!recoLoading && recoError && (
            <p role="alert" className="py-8 text-center text-stone-600">Impossible de charger les recommandations.</p>
          )}

          {!recoLoading && tabs.length > 0 && <DiscoverTabs key={scope} tabs={tabs} />}

          {showInvite && <TasteInvite />}

          {!recoLoading && !recoError && reco !== null && tabs.length === 0 && (
            <section className="panel mx-auto max-w-xl p-8 text-center">
              <h2 className="text-2xl">Pas encore de recommandations</h2>
              <p className="mt-2 mb-5 text-stone-600">
                Ajoutez des jeux à votre ludothèque et notez-les pour débloquer des suggestions personnalisées.
              </p>
              <Link to="/ludotheque" className="btn btn-primary">Ma ludothèque</Link>
            </section>
          )}
        </>
      )}
    </div>
  )
}
