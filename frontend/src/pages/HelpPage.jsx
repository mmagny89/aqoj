import { Link } from 'react-router-dom'

function Section({ id, title, children }) {
  return (
    <section aria-labelledby={id} className="panel flex flex-col gap-3 p-5 sm:p-6">
      <h2 id={id} className="text-2xl sm:text-3xl">{title}</h2>
      <div className="flex flex-col gap-3 leading-relaxed text-stone-700">{children}</div>
    </section>
  )
}

function Step({ n, title, desc }) {
  return (
    <li className="flex gap-3">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full border-[3px] border-ink bg-brand-yellow font-display text-lg" aria-hidden="true">{n}</span>
      <div>
        <p className="font-bold text-ink">{title}</p>
        <p className="text-stone-600">{desc}</p>
      </div>
    </li>
  )
}

function Engine({ label, desc, bg }) {
  return (
    <li className={`rounded-xl border-[3px] border-ink px-3.5 py-2.5 ${bg}`}>
      <p className="font-bold text-ink">{label}</p>
      <p className="text-sm text-stone-700">{desc}</p>
    </li>
  )
}

const ENGINES = [
  { label: "Placement d'Ouvriers", desc: 'Assignez vos pions à des actions limitées (Agricola, Viticulture…)', bg: 'bg-amber-100' },
  { label: 'Construction de Deck', desc: 'Améliorez votre pioche au fil du jeu (Dominion, Star Realms…)', bg: 'bg-board' },
  { label: 'Construction de Moteur', desc: "Construisez des synergies qui s'emballent (Wingspan, Terraforming Mars…)", bg: 'bg-[#8fbf9a]' },
  { label: 'Contrôle de Zone', desc: 'Dominance sur le plateau (Risk, Scythe, Root…)', bg: 'bg-brand-red' },
  { label: 'Gestion de Main', desc: 'Gérez vos cartes efficacement (7 Wonders, Race for the Galaxy…)', bg: 'bg-cream' },
  { label: 'Enchères', desc: 'Enchérissez et bluffez (Power Grid, Modern Art…)', bg: 'bg-brand-yellow' },
]

const SIMILARITY = [
  ['Mécaniques', '40 %'],
  ['Catégories', '20 %'],
  ['Joueurs', '15 %'],
  ['Complexité', '15 %'],
  ['Âge', '10 %'],
]

export default function HelpPage() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">

      <header className="flex flex-col items-center gap-3 pb-2 text-center">
        <img src="/edj-mascotte.png" alt="" width="120" height="133" className="h-28 w-auto" />
        <h1 className="text-4xl leading-tight sm:text-5xl">À quoi on joue ?</h1>
        <p className="max-w-md text-lg text-stone-700">
          Votre compagnon de soirée jeux : le bon jeu, au bon moment, pour les bonnes personnes.
        </p>
      </header>

      <Section id="h-quoi" title="C'est quoi ce site ?">
        <p>
          <strong>À quoi on joue</strong> est une application de gestion et de recommandation de jeux de société.
          Elle se connecte à votre compte <strong>BoardGameGeek (BGG)</strong> pour importer votre collection et vos notes,
          puis vous propose des recommandations adaptées à votre situation du soir.
        </p>
        <p>
          Pas de réseau social, pas de partage public : juste l'essentiel, <em>qu'est-ce qu'on joue ce soir ?</em>
        </p>
      </Section>

      <Section id="h-debut" title="Par où commencer ?">
        <ol className="m-0 flex list-none flex-col gap-4 p-0">
          <Step n={1} title="Créez un compte" desc="Inscrivez-vous avec votre adresse e-mail. Aucune donnée n'est partagée publiquement." />
          <Step n={2} title="Importez votre collection" desc="Dans « Import », renseignez votre pseudo BoardGameGeek (l'application récupère vos jeux possédés, vos notes et vos extensions) ou déposez l'export CSV de MyLudo." />
          <Step n={3} title="Explorez les recommandations" desc="Sur « À quoi on joue », les résultats arrivent d’eux-mêmes selon vos styles favoris et votre table ; changez le nombre de joueurs, la durée ou les styles, la liste se met à jour. Trois onglets : votre ludothèque, l’association (toutes les ludothèques) et tout le catalogue." />
          <Step n={4} title="Enregistrez vos parties" desc="Après chaque partie, dites si vous avez aimé ou non. Cela améliore vos recommandations futures." />
        </ol>
      </Section>

      <Section id="h-reco" title="Comment marchent les recommandations ?">
        <p>Elles combinent plusieurs signaux :</p>
        <ul className="m-0 flex list-disc flex-col gap-1 pl-5">
          <li><strong>Vos parties jouées</strong> : les jeux que vous avez aimés pèsent davantage.</li>
          <li><strong>Vos notes BGG</strong> : une note de 6/10 ou plus signale un jeu apprécié.</li>
          <li><strong>Votre collection</strong> : posséder un jeu est un signal positif léger.</li>
          <li><strong>Les jeux écartés</strong> : une note BGG sous 5, ou une partie « Pas aimé ».</li>
        </ul>
        <p>
          La note BGG affichée est une <strong>note bayésienne ajustée</strong> : un jeu avec peu de votes ne peut pas
          artificiellement dépasser un jeu très joué. Le lissage utilise C = 500 votes et une moyenne globale de 6,8.
        </p>
        <p className="rounded-xl border-[3px] border-ink bg-cream p-3 font-mono text-sm">
          score = (votes × note + 500 × 6,8) / (votes + 500)
        </p>
      </Section>

      <Section id="h-meca" title="Les familles de mécaniques (modèle Engelstein)">
        <p>
          Chaque mécanique BGG est classée selon le <strong>modèle Engelstein</strong> : <em>6 moteurs principaux</em>
          et des familles de support.
        </p>
        <h3 className="font-bold text-ink">Les 6 moteurs principaux</h3>
        <ul className="m-0 grid list-none grid-cols-1 gap-3 p-0 sm:grid-cols-2">
          {ENGINES.map(engine => <Engine key={engine.label} {...engine} />)}
        </ul>
        <h3 className="font-bold text-ink">Familles de support</h3>
        <ul className="m-0 flex list-none flex-wrap gap-2 p-0">
          {['Jeu de Cartes', 'Placement Spatial', 'Mouvement', 'Gestion de Ressources',
            'Résolution', 'Hasard & Incertitude', 'Coopération', 'Marquage de Points'].map(f => (
            <li key={f} className="badge bg-cream !text-sm">{f}</li>
          ))}
        </ul>
      </Section>

      <Section id="h-sim" title="Le score de similarité">
        <p>
          Sur chaque fiche, cinq jeux similaires sont proposés avec un <strong>pourcentage de ressemblance</strong>,
          calculé ainsi :
        </p>
        <ul className="m-0 grid list-none grid-cols-2 gap-2 p-0 text-center sm:grid-cols-5">
          {SIMILARITY.map(([label, pct]) => (
            <li key={label} className="rounded-xl border-[3px] border-ink bg-brand-yellow px-3 py-2">
              <span className="block font-display text-2xl leading-none">{pct}</span>
              <span className="text-sm font-bold">{label}</span>
            </li>
          ))}
        </ul>
        <p className="text-sm text-stone-600">L'indice de Jaccard compare les ensembles (mécaniques, catégories).</p>
      </Section>

      <Section id="h-prefs" title="Vos préférences mécaniques">
        <p>
          Dans votre <Link to="/ludotheque" className="font-bold text-brand-blue underline underline-offset-4">ludothèque</Link>,
          l'application calcule <strong>vos styles de jeu favoris</strong> à partir de :
        </p>
        <ul className="m-0 flex list-disc flex-col gap-1 pl-5">
          <li>jeux bien notés sur BGG (6/10 ou plus) : poids × 3 ;</li>
          <li>parties jouées et aimées : poids × 2 ;</li>
          <li>jeux simplement possédés : poids × 1.</li>
        </ul>
        <p>
          Ces préférences sont recalculées à chaque import BGG et à chaque partie aimée. Elles servent aussi à
          présélectionner les filtres pertinents dans les recommandations.
        </p>
      </Section>

      <Section id="h-bgg" title="BoardGameGeek (BGG)">
        <p>
          <strong>BoardGameGeek</strong> est la base de données de référence des jeux de société. Ce site utilise son API
          pour récupérer les informations des jeux : mécaniques, catégories, images, notes, nombre de joueurs.
        </p>
        <p>
          Les noms des jeux sont affichés en <strong>français si BGG en connaît un</strong>. Le titre original anglais
          reste visible en sous-titre.
        </p>
        <p className="text-sm text-stone-600">Données © BoardGameGeek. Ce site n'est pas affilié à BGG.</p>
      </Section>

      <div className="flex flex-col justify-center gap-3 pt-2 sm:flex-row">
        <Link to="/recommander" className="btn btn-primary">Trouver un jeu pour ce soir</Link>
        <Link to="/importer" className="btn btn-secondary">Importer ma collection</Link>
      </div>
    </div>
  )
}
