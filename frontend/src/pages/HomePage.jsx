import { useState, useEffect, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { getForgottenGems, getHomeRecommendations, getTonightRecommendations, saveTable } from '../api'
import GameCard from '../components/GameCard'
import { DiceIcon, DownloadIcon, SearchIcon, ShelfIcon } from '../components/icons'
import { familyLabel, familyColor, isEngine } from '../utils/engelstein'
import TasteForm, { TASTES_MAX } from '../components/TasteForm'

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
function Hero({ table, onTableChange, saved }) {
  const navigate = useNavigate()
  const { players, maxTime } = table

  const submit = (event) => {
    event.preventDefault()
    navigate(`/recommander?joueurs=${players}&duree=${maxTime}`)
  }

  return (
    <section aria-labelledby="titre-hero" className="panel relative flex gap-6 overflow-hidden px-5 py-7 shadow-token-lg sm:px-9 sm:py-9">
      <form onSubmit={submit} className="flex min-w-0 flex-1 flex-col gap-5">
        <div>
          <h1 id="titre-hero" className="text-3xl leading-[1.05] sm:text-5xl">Qu'est-ce qu'on joue ce soir&nbsp;?</h1>
          <p className="mt-2 max-w-xl text-lg text-stone-600">Dites-nous qui est autour de la table, on choisit dans la ludothèque.</p>
        </div>

        <fieldset className="m-0 flex flex-wrap items-center gap-2 border-0 p-0">
          <legend className="mb-1 float-left w-full font-bold sm:float-none sm:mb-0 sm:w-24 sm:inline-block">Joueurs</legend>
          {PLAYERS.map(n => (
            <button key={n} type="button" className="chip" aria-pressed={players === n} onClick={() => onTableChange({ ...table, players: n })}>
              {n}
            </button>
          ))}
        </fieldset>

        <fieldset className="m-0 flex flex-wrap items-center gap-2 border-0 p-0">
          <legend className="mb-1 float-left w-full font-bold sm:float-none sm:mb-0 sm:w-24 sm:inline-block">Durée</legend>
          {DURATIONS.map(({ label, value }) => (
            <button key={value} type="button" className="chip" aria-pressed={maxTime === value} onClick={() => onTableChange({ ...table, maxTime: value })}>
              {label}
            </button>
          ))}
        </fieldset>

        <div className="flex flex-wrap items-center gap-4">
          <button type="submit" className="btn btn-primary px-8">Trouver un jeu</button>
          <Link to="/rechercher" className="inline-flex min-h-11 items-center gap-2 font-bold text-brand-blue underline underline-offset-4">
            <SearchIcon width={20} height={20} /> Chercher par nom
          </Link>
        </div>
        <p role="status" className="m-0 min-h-6 text-stone-600">
          {saved === 'ok' && 'Table enregistrée : on la retrouvera à votre prochaine visite.'}
          {saved === 'error' && 'Impossible d’enregistrer la table pour l’instant.'}
        </p>
      </form>
      <img src="/edj-mascotte.png" alt="" width="250" height="278" className="hidden h-auto w-56 shrink-0 self-center object-contain lg:block xl:w-64" />
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
  const durationLabel = DURATIONS.find(d => d.value === maxTime)?.label
  const table = `${players} joueur${players > 1 ? 's' : ''}${maxTime < 240 ? `, ${durationLabel}` : ''}`

  return (
    <section aria-labelledby="titre-table" className="flex flex-col gap-6">
      <SectionTitle id="titre-table">Pour votre table</SectionTitle>
      <p className="-mt-3 text-stone-600">Pour {table} : changez la table dans le bandeau ci-dessus.</p>
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

/* ── Questionnaire de goûts ──────────────────────────────────────────── */
function TasteSection({ initial, onSaved }) {
  return (
    <section className="panel flex flex-col gap-4 p-5 sm:p-7" aria-labelledby="titre-gouts">
      <div>
        <h2 id="titre-gouts" className="text-2xl sm:text-3xl">Ce que vous aimez</h2>
        <p className="mt-1 text-stone-600">Choisissez jusqu’à {TASTES_MAX} styles : nous affinons les suggestions, sans compte BGG.</p>
      </div>
      <TasteForm initial={initial} onSaved={onSaved} />
    </section>
  )
}

// Rappel discret pour qui a déjà un profil déduit mais n'a rien déclaré : « Plus tard » le masque une semaine.
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

/* ── Onglets décennie ────────────────────────────────────────────────── */
function DecadeTabs({ byDecade }) {
  const labels = Object.keys(byDecade)
  const [active, setActive] = useState(labels[0])

  if (labels.length === 0) return null
  const games = byDecade[active] ?? []

  return (
    <section aria-labelledby="titre-epoque">
      <SectionTitle id="titre-epoque">Par époque</SectionTitle>
      <div role="tablist" aria-label="Décennies" className="mb-5 flex gap-2 overflow-x-auto pb-1">
        {labels.map(label => (
          <button key={label} type="button" role="tab" id={`epoque-${label}`} aria-selected={active === label} aria-controls="epoque-panneau"
            onClick={() => setActive(label)} className="chip shrink-0" aria-pressed={undefined}
            style={active === label ? { background: 'var(--color-ink)', color: 'var(--color-paper)' } : undefined}>
            {label}
            <span className="ml-2 text-sm opacity-80">{byDecade[label].length}</span>
          </button>
        ))}
      </div>
      <ul id="epoque-panneau" role="tabpanel" aria-labelledby={`epoque-${active}`} className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 sm:gap-5 xl:grid-cols-4 2xl:grid-cols-5">
        {games.map(({ game, reason }) => (
          <li key={game.id}><GameCard game={game} reason={reason} /></li>
        ))}
      </ul>
    </section>
  )
}

const ENTRIES = [
  { to: '/rechercher', label: 'Chercher un jeu', text: 'Par son nom, en français ou en anglais.', Icon: SearchIcon },
  { to: '/recommander', label: 'Ce soir on joue', text: 'Des idées selon la table et le temps.', Icon: DiceIcon },
  { to: '/ludotheque', label: 'Ma ludothèque', text: 'Votre collection, jeu par jeu.', Icon: ShelfIcon },
  { to: '/importer', label: 'Importer depuis BGG', text: 'Reprenez votre collection en un clic.', Icon: DownloadIcon },
]

/* ── Page ────────────────────────────────────────────────────────────── */
export default function HomePage() {
  const { user, loading: authLoading } = useAuth()

  const [reco, setReco]               = useState(null)
  const [forgotten, setForgotten]     = useState([])
  const [recoLoading, setRecoLoading] = useState(false)
  const [recoError, setRecoError]     = useState(null)
  const [reloadKey, setReloadKey]     = useState(0)

  // La table du bandeau pilote les idées « pour votre table » ; « Peu importe » (4 h) ne filtre pas la durée.
  // Elle est mémorisée sur le compte : les idées ne se chargent qu'une fois la table retrouvée.
  const [table, setTable]           = useState(DEFAULT_TABLE)
  const [tableReady, setTableReady] = useState(false)
  const [tableSaved, setTableSaved] = useState(null)
  const [tonight, setTonight]       = useState({ easy: [], popular: [] })
  const { players, maxTime } = table

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
    getHomeRecommendations()
      .then(data => {
        setReco(data)
        if (data.table) setTable(data.table)
      })
      .catch(err  => setRecoError(err.message))
      .finally(() => { setRecoLoading(false); setTableReady(true) })
    getForgottenGems().then(setForgotten).catch(() => setForgotten([]))
  }, [user, authLoading, reloadKey])

  useEffect(() => {
    if (authLoading || !user || !tableReady) return
    let current = true
    getTonightRecommendations({ players, maxTime: maxTime >= 240 ? null : maxTime })
      .then(data => { if (current) setTonight(data) })
      .catch(() => { if (current) setTonight({ easy: [], popular: [] }) })
    return () => { current = false }
  }, [user, authLoading, tableReady, players, maxTime])

  // Un même jeu ne s'affiche qu'une fois : ce que jouent les membres d'abord, les nouveautés avant les « faciles ».
  const shown      = new Set(tonight.popular.map(({ game }) => game.id))
  const recentList = (reco?.recent ?? []).filter(({ game }) => !shown.has(game.id))
  recentList.forEach(({ game }) => shown.add(game.id))
  const tonightShown = { popular: tonight.popular, easy: tonight.easy.filter(({ game }) => !shown.has(game.id)) }

  const hasTonight   = tonightShown.easy.length > 0 || tonightShown.popular.length > 0
  const hasRecent    = recentList.length > 0
  const hasDecades   = reco?.byDecade && Object.keys(reco.byDecade).length > 0
  const hasForgotten = forgotten.length > 0
  const coldStart    = reco?.coldStart === true
  const showTaste    = !recoLoading && reco !== null && (coldStart || reco.declared?.length > 0)
  const showInvite   = !recoLoading && reco !== null && !coldStart && !(reco.declared?.length > 0)

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <Hero table={table} onTableChange={changeTable} saved={tableSaved} />

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

          {!recoLoading && hasRecent && (
            <section aria-labelledby="titre-nouveautes">
              <SectionTitle id="titre-nouveautes" action={
                <Link to="/recommander" className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Voir plus de recommandations</Link>
              }>{coldStart ? 'Nouveautés à découvrir' : 'Nouveautés pour vous'}</SectionTitle>
              {coldStart ? (
                <p className="mb-4 text-stone-600">Nous ne connaissons pas encore vos goûts : voici des jeux récents, bien notés et faciles à aborder.</p>
              ) : (
                <div className="mb-4 flex flex-wrap items-center gap-2 text-stone-600">
                  <span>D'après vos goûts :</span>
                  {reco.families.map(key => (
                    <span key={key} className={`rounded-full border px-2.5 py-1 text-xs font-medium ${
                      isEngine(key) ? familyColor(key) : 'border-stone-200 bg-stone-100 text-stone-600'
                    }`}>
                      {familyLabel(key)}
                    </span>
                  ))}
                </div>
              )}
              <Carousel items={recentList} label={coldStart ? 'Nouveautés à découvrir' : 'Nouveautés pour vous'} />
            </section>
          )}

          {showTaste && <TasteSection initial={reco.declared ?? []} onSaved={() => setReloadKey(k => k + 1)} />}
          {showInvite && <TasteInvite />}

          {hasForgotten && (
            <section aria-labelledby="titre-oublies">
              <SectionTitle id="titre-oublies" action={
                <Link to="/ludotheque" className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Ma ludothèque</Link>
              }>Oubliés de votre ludothèque</SectionTitle>
              <Carousel items={forgotten} label="Oubliés de votre ludothèque" />
            </section>
          )}

          {!recoLoading && hasDecades && <DecadeTabs byDecade={reco.byDecade} />}

          {!recoLoading && !recoError && reco !== null && !hasRecent && !hasDecades && (
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
