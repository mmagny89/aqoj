import { Link } from 'react-router-dom'

function Section({ emoji, title, children }) {
  return (
    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-3">
      <div className="flex items-center gap-3">
        <span className="text-3xl">{emoji}</span>
        <h2 className="text-lg font-black text-stone-900">{title}</h2>
      </div>
      <div className="text-stone-600 text-sm leading-relaxed space-y-2">{children}</div>
    </div>
  )
}

function Step({ n, title, desc }) {
  return (
    <div className="flex gap-3">
      <div className="w-7 h-7 rounded-full bg-amber-500 text-white text-sm font-black flex-shrink-0 flex items-center justify-center mt-0.5">{n}</div>
      <div>
        <p className="font-semibold text-stone-800">{title}</p>
        <p className="text-stone-500 text-xs mt-0.5">{desc}</p>
      </div>
    </div>
  )
}

function FamilyBadge({ color, label, desc }) {
  return (
    <div className={`flex items-start gap-2 px-3 py-2 rounded-xl border ${color}`}>
      <div className="min-w-0">
        <p className="text-xs font-bold">{label}</p>
        <p className="text-xs opacity-70 mt-0.5">{desc}</p>
      </div>
    </div>
  )
}

export default function HelpPage() {
  return (
    <div className="max-w-3xl mx-auto px-4 py-10 space-y-6">

      {/* Hero */}
      <div className="text-center space-y-3 pb-4">
        <div className="text-6xl">🎲</div>
        <h1 className="text-4xl font-black text-stone-900">À Quoi On Joue ?</h1>
        <p className="text-stone-500 text-base max-w-md mx-auto">
          Votre compagnon de soirée jeux. Trouvez le bon jeu, au bon moment, pour les bonnes personnes.
        </p>
      </div>

      {/* C'est quoi ? */}
      <Section emoji="💡" title="C'est quoi ce site ?">
        <p>
          <strong>À Quoi On Joue</strong> est une application personnelle de gestion et de recommandation de jeux de société.
          Elle se connecte à votre compte <strong>BoardGameGeek (BGG)</strong> pour importer votre collection et vos notes,
          puis vous propose des recommandations adaptées à votre situation du soir.
        </p>
        <p>
          Le site est conçu pour une utilisation locale entre amis ou en famille — pas de réseau social, pas de partage public.
          Juste l'essentiel : <em>qu'est-ce qu'on joue ce soir ?</em>
        </p>
      </Section>

      {/* Comment démarrer */}
      <Section emoji="🚀" title="Par où commencer ?">
        <div className="space-y-4">
          <Step n={1} title="Créez un compte"
            desc="Inscrivez-vous avec votre adresse e-mail. Aucune donnée n'est partagée publiquement." />
          <Step n={2} title="Importez votre collection BGG"
            desc={"Rendez-vous dans « Importer » et renseignez votre pseudo BoardGameGeek. L'app récupère vos jeux possédés, vos notes et vos extensions."} />
          <Step n={3} title="Explorez les recommandations"
            desc={"Sur la page « Ce soir on joue à… », choisissez le nombre de joueurs, la durée, et vos mécaniques préférées. L'app vous propose des jeux de votre collection ET des jeux à découvrir."} />
          <Step n={4} title="Enregistrez vos parties"
            desc="Après chaque partie, notez si vous avez aimé ou non. Ces données améliorent vos recommandations futures." />
        </div>
      </Section>

      {/* Recommandations */}
      <Section emoji="🤖" title="Comment marchent les recommandations ?">
        <p>
          Les recommandations sont calculées en combinant plusieurs signaux :
        </p>
        <ul className="list-disc list-inside space-y-1 pl-1">
          <li><strong>Vos parties jouées</strong> — les jeux que vous avez aimés (note 👍) ont plus de poids</li>
          <li><strong>Vos notes BGG</strong> — les notes élevées (≥ 6/10) signalent un jeu apprécié</li>
          <li><strong>Votre collection</strong> — posséder un jeu est un signal positif léger</li>
          <li><strong>L'exclusion des jeux détestés</strong> — notes BGG &lt; 5 ou notes de session 👎 sont écartés</li>
        </ul>
        <p>
          La note BGG affichée est une <strong>note bayésienne ajustée</strong> : un jeu avec peu de votes ne peut pas
          artificiellement dépasser un jeu très joué. Le lissage utilise C = 500 votes et une moyenne globale de 6,8.
        </p>
        <div className="bg-stone-50 rounded-xl p-3 text-xs font-mono text-stone-500 border border-stone-200">
          score = (votes × note + 500 × 6.8) / (votes + 500)
        </div>
      </Section>

      {/* Familles Engelstein */}
      <Section emoji="⚙️" title="Les familles de mécaniques (modèle Engelstein)">
        <p>
          Chaque mécanique BGG est classifiée selon le <strong>modèle Engelstein</strong>, qui organise les jeux
          autour de <em>6 moteurs principaux</em> et de familles de support.
        </p>

        <p className="font-semibold text-stone-700 mt-2">Les 6 moteurs principaux :</p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <FamilyBadge color="bg-amber-50 text-amber-800 border-amber-200"
            label="🏗️ Placement d'Ouvriers" desc="Assignez vos pions à des actions limitées (Agricola, Viticulture…)" />
          <FamilyBadge color="bg-pink-50 text-pink-800 border-pink-200"
            label="🃏 Construction de Deck" desc="Améliorez votre pioche au fil du jeu (Dominion, Star Realms…)" />
          <FamilyBadge color="bg-teal-50 text-teal-800 border-teal-200"
            label="⚙️ Construction de Moteur" desc="Construisez des synergies qui s'emballent (Wingspan, Terraforming Mars…)" />
          <FamilyBadge color="bg-red-50 text-red-800 border-red-200"
            label="🗺️ Contrôle de Zone" desc="Dominance sur le plateau (Risk, Scythe, Root…)" />
          <FamilyBadge color="bg-violet-50 text-violet-800 border-violet-200"
            label="✋ Gestion de Main" desc="Gérez vos cartes efficacement (7 Wonders, Race for the Galaxy…)" />
          <FamilyBadge color="bg-lime-50 text-lime-800 border-lime-200"
            label="🔨 Enchères" desc="Enchérissez et bluffez (Power Grid, Modern Art…)" />
        </div>

        <p className="font-semibold text-stone-700 mt-2">Familles de support (mécaniques secondaires) :</p>
        <div className="flex flex-wrap gap-1.5">
          {['Jeu de Cartes','Placement Spatial','Mouvement','Gestion de Ressources',
            'Résolution','Hasard & Incertitude','Coopération','Marquage de Points'].map(f => (
            <span key={f} className="text-xs bg-stone-100 text-stone-600 border border-stone-200 px-2.5 py-1 rounded-full">{f}</span>
          ))}
        </div>
      </Section>

      {/* Similarité */}
      <Section emoji="🎯" title="Le score de similarité">
        <p>
          Sur chaque fiche de jeu, cinq jeux similaires sont proposés avec un <strong>pourcentage de similarité</strong>.
          Ce score est calculé ainsi :
        </p>
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center">
          {[
            { label: 'Mécaniques', pct: '40%', color: 'bg-amber-50 border-amber-200 text-amber-700' },
            { label: 'Catégories', pct: '20%', color: 'bg-blue-50 border-blue-200 text-blue-700' },
            { label: 'Joueurs',    pct: '15%', color: 'bg-green-50 border-green-200 text-green-700' },
            { label: 'Complexité', pct: '15%', color: 'bg-violet-50 border-violet-200 text-violet-700' },
            { label: 'Âge',        pct: '10%', color: 'bg-stone-50 border-stone-200 text-stone-600' },
          ].map(({ label, pct, color }) => (
            <div key={label} className={`rounded-xl border px-3 py-2 ${color}`}>
              <div className="text-base font-black">{pct}</div>
              <div className="text-xs">{label}</div>
            </div>
          ))}
        </div>
        <p className="text-xs text-stone-400">
          L'indice de Jaccard est utilisé pour les comparaisons par ensembles (mécaniques, catégories).
        </p>
      </Section>

      {/* Préférences */}
      <Section emoji="💜" title="Vos préférences mécaniques">
        <p>
          Dans votre <Link to="/ludotheque" className="text-amber-600 hover:underline font-medium">Ludothèque</Link>,
          l'application calcule automatiquement <strong>vos styles de jeu favoris</strong> à partir de :
        </p>
        <ul className="list-disc list-inside space-y-1 pl-1">
          <li>Jeux bien notés sur BGG (note ≥ 6) — poids × 3</li>
          <li>Parties jouées et aimées (note 👍) — poids × 2</li>
          <li>Jeux simplement possédés — poids × 1</li>
        </ul>
        <p>
          Ces préférences sont recalculées à chaque import BGG et à chaque partie aimée.
          Elles servent aussi à présélectionner les filtres pertinents dans les recommandations.
        </p>
      </Section>

      {/* BGG */}
      <Section emoji="📊" title="BoardGameGeek (BGG)">
        <p>
          <strong>BoardGameGeek</strong> est la base de données de référence des jeux de société.
          Ce site utilise l'API BGG pour récupérer les informations des jeux (mécaniques, catégories, images, notes, nombre de joueurs, etc.).
        </p>
        <p>
          Les noms des jeux sont affichés en <strong>français si disponible</strong> sur BGG.
          Le titre original anglais est affiché en sous-titre quand un titre français existe.
        </p>
        <p className="text-xs text-stone-400">
          Données © BoardGameGeek. Ce site est un projet personnel non affilié à BGG.
        </p>
      </Section>

      {/* CTA */}
      <div className="flex flex-col sm:flex-row gap-3 justify-center pt-2">
        <Link to="/recommander"
          className="flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-black px-6 py-3.5 rounded-xl transition-all shadow-sm">
          🎲 Trouver un jeu pour ce soir
        </Link>
        <Link to="/importer"
          className="flex items-center justify-center gap-2 bg-white hover:bg-stone-50 text-stone-700 font-semibold px-6 py-3.5 rounded-xl border border-stone-200 transition-all">
          📥 Importer ma collection BGG
        </Link>
      </div>

    </div>
  )
}
