import { familyLabel } from '../utils/engelstein'
import { NotInterestedIcon, ThumbDownIcon } from './icons'

/* « Parce que vous avez aimé X, et que vous aimez les mécaniques Y » : rend visible ce qui a fait proposer le jeu. */
export function WhyLine({ why }) {
  if (!why) return null
  const { liked, families = [] } = why
  const mechanics = families.map(familyLabel).join(', ')
  const plural = families.length > 1 ? 'les mécaniques' : 'la mécanique'

  return (
    <p className="m-0 text-sm text-stone-600" data-testid="why">
      {liked && <>Parce que vous avez aimé <strong className="font-bold text-ink">{liked.name}</strong></>}
      {liked && families.length > 0 && <>, et que vous aimez {plural} {mechanics}</>}
      {!liked && families.length > 0 && <>Vous aimez {plural} {mechanics}</>}
      .
    </p>
  )
}

/* Les deux retours possibles sur une proposition. Chaque bouton nomme le jeu pour les lecteurs d'écran. */
export function FeedbackButtons({ game, onFeedback }) {
  const button = 'relative z-10 inline-flex min-h-11 items-center gap-1.5 text-left text-sm font-bold text-stone-600 hover:text-ink'

  return (
    <div className="mt-auto flex flex-wrap gap-x-4">
      <button type="button" className={button} onClick={() => onFeedback(game, 'not_interested')}>
        <NotInterestedIcon aria-hidden="true" className="h-5 w-5 shrink-0" />
        <span className="underline underline-offset-4">Pas intéressé<span className="sr-only"> par {game.name}</span></span>
      </button>
      <button type="button" className={button} onClick={() => onFeedback(game, 'played_meh')}>
        <ThumbDownIcon aria-hidden="true" className="h-5 w-5 shrink-0" />
        <span className="underline underline-offset-4">Déjà joué, bof<span className="sr-only"> : {game.name}</span></span>
      </button>
    </div>
  )
}

const VERDICT_TEXT = {
  not_interested: 'ne vous sera plus proposé',
  played_meh: 'ne vous sera plus proposé (déjà joué, bof)',
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
          <button type="button" onClick={undo} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">
            Annuler
          </button>
        </>
      )}
      {notice.error && (
        <button type="button" onClick={dismiss} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">
          Fermer
        </button>
      )}
    </div>
  )
}
