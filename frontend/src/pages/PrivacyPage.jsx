import { Link } from 'react-router-dom'
import LegalPage, { LegalSection } from '../components/LegalPage'
import { publisher, host, mailer, TO_FILL } from '../legal'

export default function PrivacyPage() {
  return (
    <LegalPage title="Politique de confidentialité" updated="novembre 2026">
      <LegalSection title="Qui est responsable">
        <p>
          {publisher.name}, {publisher.form}, éditrice du site (siège : {publisher.address}). Contact pour toute question
          ou demande : {publisher.email}.
        </p>
      </LegalSection>

      <LegalSection title="Les données que nous gardons">
        <ul className="list-disc pl-5 space-y-1">
          <li>Votre adresse e-mail et votre mot de passe (conservé uniquement sous forme chiffrée, irréversible).</li>
          <li>Votre numéro de membre et le code d'invitation utilisé pour vous inscrire.</li>
          <li>Votre nom d'utilisateur BoardGameGeek, si vous importez votre collection.</li>
          <li>Votre ludothèque : les jeux que vous possédez et vos notes personnelles, y compris les parties importées de MyLudo.</li>
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
        <p>
          Les administrateurs et administratrices du site voient la liste des membres (adresse e-mail, nom d'utilisateur
          BoardGameGeek, numéro de membre, libellé de l'invitation) pour gérer les comptes et les invitations. Les
          descriptions de jeux sont traduites par un outil installé sur notre propre serveur : aucune donnée ne le quitte.
        </p>
      </LegalSection>

      <LegalSection title="Cookies et traceurs">
        <p>
          Le site n'utilise aucun cookie ni outil de mesure d'audience. Une fois connecté·e, un jeton de session est
          enregistré dans le stockage de votre navigateur : il est strictement nécessaire au fonctionnement du service,
          reste valable 8 heures, renouvelées tant que vous l'utilisez, dans la limite de 30 jours, et disparaît à la
          déconnexion.
        </p>
      </LegalSection>

      <LegalSection title="Services tiers">
        <ul className="list-disc pl-5 space-y-1">
          <li>
            <strong>BoardGameGeek</strong> : notre serveur lui envoie votre nom d'utilisateur BoardGameGeek, à votre
            demande, pour lire votre collection publique. Il fournit aussi les images des jeux : votre navigateur les
            charge directement chez lui, ce qui lui révèle votre adresse IP.
          </li>
          <li>
            <strong>{mailer.name}</strong> (sous-traitant) : il envoie nos e-mails de service (confirmation d'adresse,
            réinitialisation du mot de passe, alertes de sécurité). Il reçoit votre adresse e-mail et le contenu du
            message, uniquement pour l'envoi.
          </li>
          <li>
            <strong>{host.name}</strong> (hébergeur, en Europe) : il héberge le site et la base de données. Comme sur
            tout serveur web, les requêtes (adresse IP, page demandée, date) sont enregistrées dans les journaux
            techniques du serveur, pour la sécurité et le dépannage.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="Combien de temps">
        <p>
          Vos données restent tant que votre compte existe. Si vous le supprimez, elles sont effacées immédiatement et
          définitivement. Pour limiter les tentatives abusives, votre adresse IP est retenue 15 minutes à la connexion et
          une heure à l'inscription. Les jetons de confirmation et de réinitialisation sont supprimés au bout de 7 jours,
          les journaux des tâches automatiques au bout de 90 jours. Les journaux techniques de l'hébergeur sont conservés
          {TO_FILL}.
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
