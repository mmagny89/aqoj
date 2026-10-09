# Journal des modifications

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Le projet suit le [versionnage sémantique](https://semver.org/lang/fr/) : tant
que la version majeure vaut `0`, l'API et le schéma de base de données peuvent
changer d'une version mineure à l'autre.

Ce journal dit **ce qui a changé pour qui utilise le site**. La section d'une
version devient le texte de sa release GitHub.

## [Non publié]

### Ajouté

- **Titres français** : les jeux portent le titre de leur édition française
  quand BoardGameGeek en connaît une ; la recherche trouve aussi bien le titre
  français que l'original, sans tenir compte des accents ni de la casse.
- Garde-fous de sécurité : limitation des tentatives de connexion et
  d'inscription.
- Mentions légales, politique de confidentialité et logo « Powered by BGG ».

### Modifié

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
