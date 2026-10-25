#!/usr/bin/env bash
# sauvegarde-locale — sauvegarde la base de développement (pg_dump -Fc) hors du dépôt, puis la restaure
# dans une base jetable et compare les effectifs : une sauvegarde jamais relue n'est qu'une hypothèse.
#
#   Usage : outils/sauvegarde-locale.sh [dossier]    (défaut : ~/Sauvegardes/aqoj)
#
# Jamais `docker compose down --volumes` sur un environnement qui porte des données : cette sauvegarde
# est le filet avant toute opération sur le volume (changement de version majeure de PostgreSQL compris).
set -uo pipefail

cd "$(dirname "$0")/.."
dossier=${1:-$HOME/Sauvegardes/aqoj}
mkdir -p "$dossier" || exit 1
fichier="$dossier/aqoj-$(date +%Y%m%d-%H%M%S).dump"

echo "── Sauvegarde : $fichier"
docker compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$fichier" || { echo "ÉCHEC pg_dump" >&2; rm -f "$fichier"; exit 1; }
echo "   $(du -h "$fichier" | cut -f1)"

echo "── Restauration d'essai dans une base jetable"
docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d postgres -qc "DROP DATABASE IF EXISTS aqoj_restauration WITH (FORCE)" -c "CREATE DATABASE aqoj_restauration"' > /dev/null || exit 1
docker compose exec -T database sh -c 'pg_restore -U "$POSTGRES_USER" -d aqoj_restauration --no-owner' < "$fichier" > /tmp/restauration.log 2>&1 || { echo "ÉCHEC pg_restore (voir /tmp/restauration.log)" >&2; tail -5 /tmp/restauration.log >&2; }

compter='SELECT (SELECT count(*) FROM game) || '"'"' jeux, '"'"' || (SELECT count(*) FROM app_user) || '"'"' comptes, '"'"' || (SELECT count(*) FROM game_session) || '"'"' parties'"'"
origine=$(docker compose exec -T database sh -c "psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -tAc \"$compter\"")
copie=$(docker compose exec -T database sh -c "psql -U \"\$POSTGRES_USER\" -d aqoj_restauration -tAc \"$compter\"")
docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d postgres -qc "DROP DATABASE IF EXISTS aqoj_restauration WITH (FORCE)"' > /dev/null

echo "   base       : $origine"
echo "   restaurée  : $copie"
if [ "$origine" = "$copie" ] && [ -n "$copie" ]; then
	echo "── Sauvegarde relue avec succès."
else
	echo "── Les effectifs diffèrent : la sauvegarde n'est pas fiable." >&2
	exit 1
fi
