import { useCallback, useEffect, useRef, useState } from 'react'
import { clearGameFeedback, setGameFeedback } from '../api'

const NOTICE_MS = 8000

/* Ces retours retirent le jeu de la liste ; « J'aimerais jouer » le laisse, bouton enfoncé. */
const HIDING = new Set(['not_interested', 'played_meh', 'played_liked'])

/**
 * Retours sur les propositions : le jeu écarté disparaît aussitôt de la liste, le serveur ne le reproposera plus,
 * et un message laisse huit secondes pour annuler. `verdictOf` rend le retour courant d'un jeu (celui du serveur,
 * sauf s'il vient d'être changé ici).
 */
export function useGameFeedback() {
  const [marked, setMarked] = useState(() => new Map())
  const [notice, setNotice] = useState(null)
  const timer = useRef(null)

  useEffect(() => () => clearTimeout(timer.current), [])

  const announce = useCallback((next) => {
    clearTimeout(timer.current)
    setNotice(next)
    if (next) timer.current = setTimeout(() => setNotice(null), NOTICE_MS)
  }, [])

  // Le jeu est gardé avec son retour : la section « Mes envies » s'alimente sans attendre un rechargement.
  const apply = (game, verdict) => setMarked(prev => new Map(prev).set(game.id, { verdict, game }))
  const persist = (game, verdict) => (verdict ? setGameFeedback(game.id, verdict) : clearGameFeedback(game.id))

  /* `verdict` = null retire le retour ; `previous` = le retour d'avant, pour annuler. */
  const send = useCallback((game, verdict, previous = null) => {
    apply(game, verdict)
    announce(verdict ? { game, verdict, previous } : null)
    persist(game, verdict).catch(() => {
      apply(game, previous)
      announce({ game, error: true })
    })
  }, [announce])

  const undo = useCallback(() => {
    if (!notice?.game) return
    const { game, previous, verdict } = notice
    apply(game, previous)
    announce(null)
    persist(game, previous).catch(() => {
      apply(game, verdict)
      announce({ game, error: true })
    })
  }, [notice, announce])

  const verdictOf = useCallback((id, fromServer = null) => (marked.has(id) ? marked.get(id).verdict : fromServer), [marked])
  const isHidden = useCallback((id) => HIDING.has(marked.get(id)?.verdict), [marked])
  // Les jeux marqués « J'aimerais jouer » depuis cette page.
  const wanted = [...marked.values()].filter(m => m.verdict === 'want_to_play').map(m => m.game)
  const dismiss = useCallback(() => announce(null), [announce])

  return { isHidden, verdictOf, wanted, send, undo, dismiss, notice }
}
