import { Link } from 'react-router-dom'
import LegalPage, { LegalSection } from '../components/LegalPage'
import { publisher } from '../legal'

export default function PrivacyPage() {
  return (
    <LegalPage title="Politique de confidentialité" updated="octobre 2026">
      <LegalSection title="Qui est responsable">
        <p>
          {publisher.name}, éditrice du site. Contact pour toute question ou demande : {publisher.email}.
        </p>
      </LegalSection>

      <LegalSection title="Les données que nous gardons">
        <ul className="list-disc pl-5 space-y-1">
          <li>Votre adresse e-mail et votre mot de passe (conservé uniquement sous forme chiffrée, irréversible).</li>
          <li>Votre nom d'utilisateur BoardGameGeek, si vous importez votre collection.</li>
          <li>Votre ludothèque : les jeux que vous possédez et vos notes personnelles.</li>
          <li>Vos parties jouées : jeu, nombre de joueurs, note, commentaire, date.</li>
          <li>Vos préférences, calculées à partir de votre ludothèque pour les recommandations.</li>
          <li>Vos goûts, si vous les indiquez, et la table (nombre de joueurs, durée) que vous choisissez sur l'accueil.</li>
        </ul>
        <p>
          Elles servent à faire fonctionner votre compte et à vous recommander des jeux, rien d'autre. Elles ne sont ni
          vendues, ni utilisées pour de la publicité. Le fichier CSV de MyLudo que vous déposez n'est pas conservé : seuls
          les jeux que vous confirmez entrent dans votre ludothèque. Vos parties comptent, sans votre nom, dans les totaux
          « joué par les autres membres » de l'accueil, qui n'apparaissent qu'à partir de deux joueurs distincts.
        </p>
      </LegalSection>

      <LegalSection title="Cookies et traceurs">
        <p>
          Le site n'utilise aucun cookie ni outil de mesure d'audience. Une fois connecté·e, un jeton de session est
          enregistré dans le stockage de votre navigateur : il est strictement nécessaire au fonctionnement du service,
          expire au bout de 24 heures et disparaît à la déconnexion.
        </p>
      </LegalSection>

      <LegalSection title="Services tiers">
        <ul className="list-disc pl-5 space-y-1">
          <li>
            <strong>BoardGameGeek</strong> : notre serveur lui envoie votre nom d'utilisateur BoardGameGeek, à votre
            demande, pour lire votre collection publique. Il fournit aussi les images des jeux : votre navigateur les
            charge directement chez lui, ce qui lui révèle votre adresse IP.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="Combien de temps">
        <p>
          Vos données restent tant que votre compte existe. Si vous le supprimez, elles sont effacées immédiatement et
          définitivement. Les adresses IP ne sont pas conservées au-delà de la limitation temporaire des tentatives de
          connexion (quelques minutes).
        </p>
      </LegalSection>

      <LegalSection title="Vos droits">
        <p>
          Vous pouvez à tout moment obtenir une copie de vos données et supprimer votre compte depuis{' '}
          <Link to="/compte" className="text-amber-700 underline">la page Mon compte</Link>. Pour rectifier une
          information, vous opposer à un traitement ou saisir l'éditrice, écrivez à {publisher.email}. En l'absence de
          réponse satisfaisante, vous pouvez saisir la CNIL (cnil.fr).
        </p>
      </LegalSection>
    </LegalPage>
  )
}
