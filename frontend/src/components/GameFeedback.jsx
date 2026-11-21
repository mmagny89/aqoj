import { useEffect, useId, useState } from 'react'
import { familyColor, familyLabel } from '../utils/engelstein'
import { BookmarkIcon, NotInterestedIcon, SparkIcon, ThumbDownIcon, ThumbUpIcon } from './icons'

/* « Parce que vous avez aimé X, et que vous aimez les mécaniques Y » : un encadré à part, pour que la raison de la proposition se voie. */
const WHY_CHIPS = 3

export function WhyLine({ why }) {
  if (!why) return null
  const { liked, families = [] } = why
  const shown = families.slice(0, WHY_CHIPS)
  const extra = families.length - shown.length
  const plural = families.length > 1 ? 'les mécaniques' : 'la mécanique'

  return (
    <div data-testid="why" className="flex flex-col gap-1 rounded-xl border-2 border-ink bg-brand-yellow/25 p-2 text-sm leading-snug">
      <p className="m-0 flex items-center gap-1 whitespace-nowrap text-xs font-bold">
        <SparkIcon aria-hidden="true" className="h-3.5 w-3.5 shrink-0" />
        Pourquoi ce jeu ?
      </p>
      <p className="m-0 break-words">
        {liked && <>Parce que vous avez aimé <strong className="font-bold">{liked.name}</strong></>}
        {liked && families.length > 0 && <>, et que vous aimez {plural}&nbsp;:</>}
        {liked && families.length === 0 && '.'}
        {!liked && <>Vous aimez {plural}&nbsp;:</>}
      </p>
      {families.length > 0 && (
        <ul className="m-0 flex list-none flex-wrap gap-1 p-0">
          {shown.map(f => (
            <li key={f} className={`rounded-full border px-2 py-0.5 text-xs font-medium ${familyColor(f)}`}>{familyLabel(f)}</li>
          ))}
          {extra > 0 && <li className="rounded-full border border-stone-300 bg-paper px-2 py-0.5 text-xs font-medium">+{extra}</li>}
        </ul>
      )}
    </div>
  )
}

/* Les quatre retours possibles : icônes seules, expliquées par une infobulle au survol et au focus (Échap la ferme). */
const OPTIONS = [
  { verdict: 'not_interested', label: 'Pas intéressé', help: 'Ne plus me proposer ce jeu.', Icon: NotInterestedIcon },
  { verdict: 'played_meh', label: 'Déjà joué, bof', help: 'J’y ai joué sans accrocher : ne plus me le proposer.', Icon: ThumbDownIcon },
  { verdict: 'want_to_play', label: 'J’aimerais jouer', help: 'Ce jeu me tente : mes goûts en tiennent compte.', Icon: BookmarkIcon },
  { verdict: 'played_liked', label: 'J’ai joué, et j’aime', help: 'J’y ai joué et j’ai aimé : mes goûts en tiennent compte, ne plus me le proposer.', Icon: ThumbUpIcon },
]

export function FeedbackButtons({ game, verdict = null, onFeedback }) {
  const [tip, setTip] = useState(null)
  const tipId = useId()
  const hideTip = () => setTip(null)

  // Échap ferme l'infobulle, qu'elle soit ouverte par le survol ou par le focus.
  useEffect(() => {
    if (!tip) return
    const onKey = (e) => { if (e.key === 'Escape') setTip(null) }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [tip])

  return (
    <div
      role="group"
      aria-label={`Votre avis sur ${game.name}`}
      className="relative -mx-2 mt-auto flex"
      onMouseLeave={hideTip}
    >
      {tip && (
        <div id={tipId} role="tooltip" className="pointer-events-none absolute inset-x-0 bottom-full z-20 mb-1 rounded-xl border-[3px] border-ink bg-paper p-2 text-xs leading-snug shadow-token-sm">
          <strong className="block">{tip.label}</strong>
          {tip.help}
        </div>
      )}
      {OPTIONS.map(option => (
        <button
          key={option.verdict}
          type="button"
          aria-label={`${option.label} : ${game.name}`}
          aria-pressed={verdict === option.verdict}
          aria-describedby={tip?.verdict === option.verdict ? tipId : undefined}
          onMouseEnter={() => setTip(option)}
          onFocus={() => setTip(option)}
          onBlur={hideTip}
          onClick={() => onFeedback(game, verdict === option.verdict ? null : option.verdict, verdict)}
          className="relative z-10 grid min-h-11 flex-1 cursor-pointer place-items-center rounded-lg text-stone-600 hover:text-ink aria-pressed:bg-brand-yellow aria-pressed:text-ink"
        >
          <option.Icon aria-hidden="true" className="h-5 w-5" />
        </button>
      ))}
    </div>
  )
}

const VERDICT_TEXT = {
  not_interested: 'ne vous sera plus proposé',
  played_meh: 'ne vous sera plus proposé (déjà joué, bof)',
  played_liked: 'compte parmi vos goûts et ne vous sera plus proposé (déjà joué)',
  want_to_play: 'est noté comme une envie de jeu',
}

/* Message de confirmation, avec annulation ; une zone `status` pour que le retrait soit annoncé. */
export function FeedbackNotice({ feedback }) {
  const { notice, undo, dismiss } = feedback
  if (!notice) return <div role="status" className="sr-only" />

  return (
    <div role="status" className="panel fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md flex-wrap items-center justify-between gap-x-4 px-4 py-2">
      {notice.error ? (
        <span>Impossible d’enregistrer ce retour pour l’instant.</span>
      ) : (
        <>
          <span>« {notice.game.name} » {VERDICT_TEXT[notice.verdict]}.</span>
          <button type="button" onClick={undo} className="inline-flex min-h-11 cursor-pointer items-center font-bold text-brand-blue underline underline-offset-4">
            Annuler
          </button>
        </>
      )}
      {notice.error && (
        <button type="button" onClick={dismiss} className="inline-flex min-h-11 cursor-pointer items-center font-bold text-brand-blue underline underline-offset-4">
          Fermer
        </button>
      )}
    </div>
  )
}
