import { useCallback, useEffect, useRef, useState } from 'react'
import { clearGameFeedback, setGameFeedback } from '../api'

const NOTICE_MS = 8000

/**
 * Retours sur les propositions (« pas intéressé », « déjà joué, bof ») : le jeu disparaît aussitôt de la liste,
 * le serveur ne le reproposera plus, et un message laisse huit secondes pour annuler.
 */
export function useGameFeedback() {
  const [hidden, setHidden] = useState(() => new Set())
  const [notice, setNotice] = useState(null)
  const timer = useRef(null)

  useEffect(() => () => clearTimeout(timer.current), [])

  const announce = useCallback((next) => {
    clearTimeout(timer.current)
    setNotice(next)
    if (next) timer.current = setTimeout(() => setNotice(null), NOTICE_MS)
  }, [])

  const setVisible = (id, visible) => setHidden(prev => {
    const next = new Set(prev)
    if (visible) next.delete(id)
    else next.add(id)
    return next
  })

  const send = useCallback((game, verdict) => {
    setVisible(game.id, false)
    announce({ game, verdict })
    setGameFeedback(game.id, verdict).catch(() => {
      setVisible(game.id, true)
      announce({ game, error: true })
    })
  }, [announce])

  const undo = useCallback(() => {
    if (!notice?.game) return
    const { game } = notice
    setVisible(game.id, true)
    announce(null)
    clearGameFeedback(game.id).catch(() => {
      setVisible(game.id, false)
      announce({ game, error: true })
    })
  }, [notice, announce])

  const isHidden = useCallback((id) => hidden.has(id), [hidden])
  const dismiss = useCallback(() => announce(null), [announce])

  return { isHidden, send, undo, dismiss, notice }
}
