#!/usr/bin/env bash
# ci-local — rejoue les portes de .github/workflows/qualite.yml dans le conteneur php, mais avec
# l'environnement MINIMAL de la CI (`env -i` : aucune des variables que compose.yml injecte) et une
# base jetable. C'est ce qui révèle une variable oubliée dans le workflow, ce que `make test` ne voit
# jamais puisque le conteneur en porte déjà tout.
#
#   Usage : outils/ci-local.sh
#
# Rien n'est écrit dans la base de développement : la base `app_ci_test` est créée puis supprimée.
set -uo pipefail

cd "$(dirname "$0")/.."

url_dev=$(docker compose exec -T php sh -c 'printf %s "$DATABASE_URL"') || exit 1
case "$url_dev" in */app\?*) ;; *) echo "ci-local : DATABASE_URL inattendue (base « app » attendue)." >&2; exit 1 ;; esac
url_ci=${url_dev/\/app\?/\/app_ci?}

# Les variables que le workflow déclare dans son `env:` : ni plus, ni moins.
ci() {
	docker compose exec -T php env -i PATH="$PATH" HOME=/tmp \
		APP_ENV=test DATABASE_URL="$url_ci" MAILER_DSN='null://null' MAILER_FROM='noreply@aqoj.test' \
		"$@"
}

echecs=0
porte() {
	local nom=$1
	shift
	if "$@" > /tmp/ci-local.log 2>&1; then
		echo "  ok  $nom"
	else
		echo "  ÉCHEC  $nom"
		tail -n 25 /tmp/ci-local.log | sed 's/^/        /'
		echecs=$((echecs + 1))
	fi
}

echo "── Portes de la CI, environnement vierge"
porte "Cohérence du dépôt (composer validate)" ci composer validate --strict --no-check-publish
porte "Lints Symfony (yaml)" ci php bin/console lint:yaml config
porte "Lints Symfony (conteneur)" ci php bin/console lint:container
porte "Audit des dépendances" ci composer audit
porte "Analyse statique" ci sh -c 'APP_DEBUG=1 php bin/console cache:warmup --env=dev && vendor/bin/phpstan analyse --no-progress'
porte "Base de test créée et migrée" ci sh -c 'php bin/console doctrine:database:create --env=test --if-not-exists --no-interaction && php bin/console doctrine:migrations:migrate --env=test --no-interaction'
porte "Tests PHP" ci vendor/bin/phpunit

# Nettoyage : la base jetable ne doit pas survivre à l'essai.
docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d postgres -qc "DROP DATABASE IF EXISTS app_ci_test WITH (FORCE)"' > /dev/null 2>&1

if [ "$echecs" -gt 0 ]; then
	echo "── $echecs porte(s) en échec."
	exit 1
fi
echo "── Toutes les portes passent."
