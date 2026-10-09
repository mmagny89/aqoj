# Contribuer

Les contributions passent par une issue avant tout code : elle permet de
vérifier que la demande s'accorde avec le projet.

## Signaler un problème ou proposer une idée

Ouvrez une issue avec le gabarit adapté. Pour une faille de sécurité, voir
[`SECURITY.md`](SECURITY.md).

## Proposer du code

1. Environnement : [`README.md`](README.md), puis `docker compose up -d --wait`.
2. Branche tirée de `develop` : `feat/*`, `fix/*`, `docs/*`, `refactor/*`,
   `chore/*`.
3. Commits en [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/),
   en français : `feat(recherche): …`, `fix(bgg): …`.
4. Portes qualité au vert avant la demande de fusion :
   ```bash
   make qualite
   ```
   (PHPStan, PHPUnit, ESLint et Vitest ; le détail est dans le `Makefile`.)
5. Ajouter l'entrée correspondante dans la section « Non publié » de
   [`CHANGELOG.md`](CHANGELOG.md), rédigée pour qui utilise le site.
6. Demande de fusion vers `develop`, jamais vers `main`.

Un appel à BoardGameGeek ne se fait jamais pendant la requête d'un joueur :
voir `backend/CLAUDE.md`.
