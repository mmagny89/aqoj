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

  const apply = (id, verdict) => setMarked(prev => new Map(prev).set(id, verdict))
  const persist = (game, verdict) => (verdict ? setGameFeedback(game.id, verdict) : clearGameFeedback(game.id))

  /* `verdict` = null retire le retour ; `previous` = le retour d'avant, pour annuler. */
  const send = useCallback((game, verdict, previous = null) => {
    apply(game.id, verdict)
    announce(verdict ? { game, verdict, previous } : null)
    persist(game, verdict).catch(() => {
      apply(game.id, previous)
      announce({ game, error: true })
    })
  }, [announce])

  const undo = useCallback(() => {
    if (!notice?.game) return
    const { game, previous, verdict } = notice
    apply(game.id, previous)
    announce(null)
    persist(game, previous).catch(() => {
      apply(game.id, verdict)
      announce({ game, error: true })
    })
  }, [notice, announce])

  const verdictOf = useCallback((id, fromServer = null) => (marked.has(id) ? marked.get(id) : fromServer), [marked])
  const isHidden = useCallback((id) => HIDING.has(marked.get(id)), [marked])
  const dismiss = useCallback(() => announce(null), [announce])

  return { isHidden, verdictOf, send, undo, dismiss, notice }
}
