import { Link } from 'react-router-dom'
import { DiceIcon, SearchIcon, ShelfIcon, SparkIcon } from '../components/icons'

const FONCTIONS = [
  { Icon: SparkIcon, titre: 'Des idées pour ce soir', texte: 'Indiquez le nombre de joueurs et le temps dont vous disposez : le site propose des jeux adaptés à votre table, selon vos goûts.' },
  { Icon: SearchIcon, titre: 'Explorer le catalogue', texte: 'Une recherche en français ou en anglais, des fiches détaillées (durée, complexité, mécaniques) et des jeux proches de ceux que vous aimez.' },
  { Icon: ShelfIcon, titre: 'Votre ludothèque', texte: 'Importez votre collection depuis BoardGameGeek ou MyLudo (export CSV), ou déclarez simplement vos goûts si vous n’en avez pas.' },
  { Icon: DiceIcon, titre: 'Vos parties', texte: 'Gardez la trace de ce que vous avez joué : le site s’en sert pour vous proposer des jeux oubliés ou à redécouvrir.' },
]

const CAPTURES = [
  { src: '/captures/accueil.png', alt: 'Accueil : idées de jeux pour une table de quatre joueurs', legende: 'Les suggestions du soir, selon la table.' },
  { src: '/captures/fiche.png', alt: 'Fiche d’un jeu avec ses caractéristiques et des jeux proches', legende: 'Une fiche complète, en français.' },
  { src: '/captures/recommander.png', alt: 'Page À quoi on joue : résultats classés selon la table et les styles', legende: 'Des résultats qui suivent vos envies.' },
]

export default function LandingPage() {
  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-12 px-4 py-8 sm:px-6 lg:px-10 lg:py-12">
      <section aria-labelledby="titre-accueil" className="panel flex flex-col items-center gap-5 px-5 py-8 text-center sm:px-10 sm:py-12">
        <img src="/edj-mascotte.png" alt="" width="120" height="120" className="h-24 w-auto sm:h-32" />
        <h1 id="titre-accueil" className="text-3xl leading-tight sm:text-5xl">À quoi on joue&nbsp;?</h1>
        <p className="m-0 max-w-2xl text-lg text-stone-700">
          Le site de recommandation de jeux de société de l’association <strong>Envie de jouer</strong> : trouvez le bon jeu pour votre table, en quelques clics.
        </p>
        <p role="note" className="m-0 max-w-2xl rounded-2xl border-[3px] border-ink bg-brand-yellow px-4 py-3 font-bold">
          Accès réservé aux adhérents de l’association Envie de jouer.
        </p>
        <div className="flex flex-wrap items-center justify-center gap-3">
          <Link to="/connexion" className="btn btn-primary">Se connecter</Link>
          <Link to="/inscription" className="btn btn-secondary">Créer mon compte</Link>
        </div>
      </section>

      <section aria-labelledby="titre-fonctions">
        <h2 id="titre-fonctions" className="mb-5 text-2xl sm:text-3xl">Ce que vous y trouverez</h2>
        <ul className="m-0 grid list-none grid-cols-1 gap-5 p-0 sm:grid-cols-2">
          {FONCTIONS.map(({ Icon, titre, texte }) => (
            <li key={titre} className="token-card h-full gap-2 p-5">
              <Icon width={32} height={32} />
              <h3 className="font-display text-2xl leading-tight">{titre}</h3>
              <p className="m-0 text-stone-700">{texte}</p>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="titre-captures">
        <h2 id="titre-captures" className="mb-5 text-2xl sm:text-3xl">Un aperçu</h2>
        <ul className="m-0 grid list-none grid-cols-1 gap-6 p-0 md:grid-cols-3">
          {CAPTURES.map(({ src, alt, legende }) => (
            <li key={src}>
              <figure className="m-0 flex h-full flex-col gap-2">
                <img src={src} alt={alt} loading="lazy" width="928" height="696" className="w-full rounded-2xl border-[3px] border-ink bg-paper shadow-token-sm" />
                <figcaption className="text-sm text-stone-700">{legende}</figcaption>
              </figure>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="titre-acces" className="panel flex flex-col gap-3 p-6 sm:p-8">
        <h2 id="titre-acces" className="text-2xl sm:text-3xl">Comment y accéder&nbsp;?</h2>
        <ol className="m-0 flex list-decimal flex-col gap-2 pl-6 text-stone-700">
          <li>Adhérez à l’association <strong>Envie de jouer</strong>.</li>
          <li>Demandez à l’association votre code d’invitation.</li>
          <li><Link to="/inscription" className="font-bold text-brand-blue underline underline-offset-4">Créez votre compte</Link> avec ce code et confirmez votre adresse e-mail.</li>
        </ol>
      </section>
    </div>
  )
}
