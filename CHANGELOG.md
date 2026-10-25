# Journal des modifications

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Le projet suit le [versionnage sémantique](https://semver.org/lang/fr/) : tant
que la version majeure vaut `0`, l'API et le schéma de base de données peuvent
changer d'une version mineure à l'autre.

Ce journal dit **ce qui a changé pour qui utilise le site**. La section d'une
version devient le texte de sa release GitHub.

## [Non publié]

### Ajouté

- **Édition française** : la fiche d'un jeu indique l'éditeur et l'année de son édition française, et montre la boîte française
  quand BoardGameGeek en a l'image. Le titre français est celui de l'édition au titre le plus répandu, plus la plus récente.
- **Icône du site** : la mascotte de l'association sert de favicon (onglet du navigateur, écran d'accueil d'un téléphone).
- **Import MyLudo** : le menu « Import » a trois onglets (compte BGG, collection MyLudo, parties MyLudo à venir). On dépose
  l'export CSV de sa ludothèque MyLudo ; les jeux sont rapprochés du catalogue, puis on départage les homonymes, on
  cherche les jeux introuvables ou on les laisse de côté. Rien n'est ajouté avant confirmation, et la note personnelle est reprise.
- **Ludothèque de l'association** : les administrateurs la renseignent par un export MyLudo ou par le compte BGG de
  l'association, la consultent et retirent un jeu mal importé.
- **Table de l'accueil** : de 1 à 8 joueurs et de 15 min à « peu importe », enregistrée sur le compte et reprise par la page
  de recommandation. Deux nouvelles rangées la suivent : « Ce que jouent les autres membres » (au moins deux joueurs
  distincts, sans jamais dire qui) et « Faciles à expliquer ».
- **Mes goûts** : un questionnaire de six styles au plus, sur l'accueil et dans Mon compte, rappelé tant qu'il n'est pas
  rempli. Il sert de profil à qui n'a ni compte BGG ni historique, et passe avant les goûts déduits de l'historique.
- **Démarrage à froid** : sans profil, l'accueil propose des nouveautés abordables au lieu de « pas de recommandations ».
- **Titres français** : les jeux portent le titre de leur édition française
  quand BoardGameGeek en connaît une ; la recherche trouve aussi bien le titre
  français que l'original, sans tenir compte des accents ni de la casse.
- Garde-fous de sécurité : limitation des tentatives de connexion et
  d'inscription.
- Mentions légales, politique de confidentialité et mention « Powered by BGG » avec lien retour, en pied de page.
- **Mon compte** : téléchargement de ses données (JSON) et suppression définitive du compte, avec confirmation par mot de passe.

### Modifié

- Les nouveautés couvrent les **cinq dernières années** (trois auparavant) avec un bonus de récence continu, et la page
  « À découvrir » ajoute des jeux récents à sa sélection au lieu d'être dominée par les classiques.
- La recherche classe le titre exact en premier, puis les titres qui commencent par la saisie.
- Les suggestions de l'accueil excluent les extensions, et un même jeu n'y figure qu'une fois.
- L'export des données du compte inclut les goûts et la table enregistrés ; la politique de confidentialité les mentionne.
- Les données BoardGameGeek sont rafraîchies chaque nuit, à la cadence qu'il
  demande, au lieu de l'être pendant la consultation d'une fiche : les pages
  s'ouvrent plus vite et ne dépendent plus de sa disponibilité.
- L'import d'une collection BoardGameGeek n'interroge plus le service qu'une
  fois.

## [0.1.0] - 2026-06-07

### Ajouté

- Ludothèque personnelle importée depuis BoardGameGeek, avec notes personnelles.
- Recommandations dans la collection et hors collection, fiches de jeux,
  extensions et jeux similaires.
- Modèle de mécaniques (Engelstein) et thématiques configurables en
  administration.
- Journal de parties.

[Non publié]: https://github.com/mmagny89/aqoj/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mmagny89/aqoj/releases/tag/v0.1.0
