import { Link, useOutletContext, useSearchParams } from 'react-router-dom'
import { GamesPanel, JobsPanel } from '../components/AdminCatalogue'
import AdminMembers from '../components/AdminMembers'
import { Notice, PageHeader, ProgressBar, Section, Spinner, StatCard, StatGrid, fmtDate, fmtNum, pct } from '../components/AdminUi'

/* Pages de l'administration : une par entrée du sous-menu (AdminLayout). Les chiffres viennent de `getAdminStats`, chargé une fois. */

function WithStats({ children }) {
  const { stats, error } = useOutletContext()
  if (error) return <p role="alert" className="panel border-brand-red bg-amber-100 p-4 font-bold">{error}</p>
  if (!stats) return <Spinner />
  return children(stats)
}

const FILTERS = ['pending', 'stale', 'no_mechanics', 'no_image', 'no_description', 'unlinked_expansions']
const explorer = (filter) => `/admin/explorer?filtre=${filter}`

/* ── Vue d'ensemble : ce qui demande une action, puis les chiffres clés ─ */
export function AdminOverviewPage() {
  return (
    <WithStats>
      {({ games: g, expansions: ex, users: u, sessions: s, mappings: m, themes: th, generated_at: generatedAt }) => {
        const todo = [
          Number(g.pending) > 0 && { id: 'pending', to: explorer('pending'), text: <><strong>{fmtNum(g.pending)} jeux</strong> attendent leur enrichissement BGG.</> },
          Number(g.stale_count) > 500 && { id: 'stale', to: explorer('stale'), text: <><strong>{fmtNum(g.stale_count)} jeux</strong> n’ont pas été resynchronisés depuis 30 jours.</> },
          Number(ex.unlinked) > 0 && { id: 'unlinked', to: explorer('unlinked_expansions'), text: <><strong>{fmtNum(ex.unlinked)} extensions</strong> n’ont pas de jeu de base.</> },
          Number(m.unmapped_bgg_mechanics) > 0 && { id: 'mechanics', to: '/admin/mappings/mecaniques?tab=unmapped', cta: 'Mapper', text: <><strong>{fmtNum(m.unmapped_bgg_mechanics)} mécaniques BGG</strong> ne sont pas mappées.</> },
          Number(th.unmapped_bgg_categories) > 0 && { id: 'categories', to: '/admin/mappings/themes', cta: 'Mapper', text: <><strong>{fmtNum(th.unmapped_bgg_categories)} catégories BGG</strong> ne sont pas mappées : absentes des filtres.</> },
        ].filter(Boolean)

        return (
          <>
            <PageHeader title="Vue d’ensemble" intro={`Chiffres au ${fmtDate(generatedAt)}.`} />

            <Section id="a-traiter" title="À traiter">
              {todo.length === 0
                ? <p className="m-0 font-bold">Rien à signaler : le catalogue est à jour.</p>
                : <ul className="m-0 flex list-none flex-col gap-2 p-0">{todo.map(({ id, to, cta, text }) => <Notice key={id} to={to} cta={cta}>{text}</Notice>)}</ul>}
            </Section>

            <Section id="chiffres" title="Chiffres clés">
              <StatGrid>
                <StatCard label="Jeux de base" value={fmtNum(g.total)} sub={`${pct(g.enriched, g.total) ?? '—'} enrichis`} to="/admin/catalogue" />
                <StatCard label="Membres" value={fmtNum(u.total)} sub={`${fmtNum(u.with_bgg)} avec un compte BGG`} to="/admin/membres" />
                <StatCard label="Parties" value={fmtNum(s.total)} sub={`${fmtNum(s.this_month)} ce mois-ci`} to="/admin/communaute" />
                <StatCard label="Mappings" value={fmtNum(Number(m.total_mappings) + Number(th.total_mappings))} sub="mécaniques et thèmes" to="/admin/mappings" />
              </StatGrid>
            </Section>
          </>
        )
      }}
    </WithStats>
  )
}

/* ── Catalogue : état de l'enrichissement des jeux et des extensions ──── */
export function AdminCataloguePage() {
  return (
    <WithStats>
      {({ games: g, expansions: ex }) => (
        <>
          <PageHeader title="Catalogue" intro="Où en est l’enrichissement depuis BGG." />

          <Section id="jeux" title="Jeux de base">
            <StatGrid>
              <StatCard label="Jeux de base" value={fmtNum(g.total)} />
              <StatCard label="Enrichis (API BGG)" value={fmtNum(g.enriched)} sub={pct(g.enriched, g.total)} />
              <StatCard label="Fiches CSV en attente" value={fmtNum(g.pending)} warn={Number(g.pending) > 0}
                sub={Number(g.pending) > 0 ? 'À enrichir · voir la liste' : 'Tout est enrichi'} to={explorer('pending')} />
              <StatCard label="À resynchroniser" value={fmtNum(g.stale_count)} warn={Number(g.stale_count) > 500}
                sub={Number(g.stale_count) > 0 ? 'Plus de 30 jours · voir la liste' : 'Tout est à jour'} to={explorer('stale')} />
            </StatGrid>

            <div className="flex flex-col gap-4">
              <ProgressBar label="Enrichis (source BGG API)" value={Number(g.enriched)} max={Number(g.total)}
                hint="Le reste est enrichi par la tâche planifiée enrich-pending : rien à faire à la main." />
              <ProgressBar label="Avec mécaniques BGG" value={Number(g.total) - Number(g.no_mechanics)} max={Number(g.total)}
                hint="Un jeu compte dès qu’il a des mécaniques, mappées ou non. Les fiches CSV n’en ont pas avant leur enrichissement."
                to={explorer('no_mechanics')} />
              <ProgressBar label="Avec image" value={Number(g.total) - Number(g.no_image)} max={Number(g.total)}
                hint="Jeux enrichis sans image sur BGG, ou fiches pas encore enrichies."
                to={explorer('no_image')} />
              <ProgressBar label="Avec description" value={Number(g.total) - Number(g.no_description)} max={Number(g.total)}
                hint="Jeux enrichis sans description sur BGG, ou fiches pas encore enrichies."
                to={explorer('no_description')} />
            </div>

            <StatGrid columns={3}>
              <StatCard label="Dernier enrichissement" value={fmtDate(g.last_synced)} />
              <StatCard label="Enrichissement le plus ancien" value={fmtDate(g.oldest_synced)} />
              <StatCard label="Données manquantes" value={fmtNum(Number(g.no_mechanics) + Number(g.no_image) + Number(g.no_description))}
                sub={`${fmtNum(g.no_mechanics)} sans mécaniques · ${fmtNum(g.no_image)} sans image · ${fmtNum(g.no_description)} sans description`} />
            </StatGrid>
          </Section>

          <Section id="extensions" title="Extensions">
            <StatGrid>
              <StatCard label="Extensions" value={fmtNum(ex.total)} />
              <StatCard label="Enrichies (API BGG)" value={fmtNum(ex.enriched)} sub={pct(ex.enriched, ex.total)} />
              <StatCard label="Fiches CSV en attente" value={fmtNum(ex.pending)} warn={Number(ex.pending) > 0}
                sub={Number(ex.pending) > 0 ? 'À enrichir' : 'Tout est enrichi'} />
              <StatCard label="À resynchroniser" value={fmtNum(ex.stale_count)} warn={Number(ex.stale_count) > 200} sub="Plus de 30 jours" />
            </StatGrid>

            <div className="flex flex-col gap-4">
              <ProgressBar label="Liées à un jeu de base" value={Number(ex.linked)} max={Number(ex.total)} />
              <ProgressBar label="Enrichies (source BGG API)" value={Number(ex.enriched)} max={Number(ex.total)} />
              <ProgressBar label="Avec mécaniques BGG" value={Number(ex.total) - Number(ex.no_mechanics)} max={Number(ex.total)} />
              <ProgressBar label="Avec image" value={Number(ex.total) - Number(ex.no_image)} max={Number(ex.total)} />
            </div>

            {Number(ex.unlinked) > 0 && (
              <ul className="m-0 list-none p-0">
                <Notice to={explorer('unlinked_expansions')}>
                  <strong>{fmtNum(ex.unlinked)} extensions</strong> n’ont pas de lien vers leur jeu de base. Pour relancer : <code>app:bgg:relink-expansions</code>
                </Notice>
              </ul>
            )}
          </Section>
        </>
      )}
    </WithStats>
  )
}

/* ── Explorer : le filtre vit dans l'adresse (?filtre=), donc se partage ─ */
export function AdminExplorerPage() {
  const [params, setParams] = useSearchParams()
  const requested = params.get('filtre')
  const filter = FILTERS.includes(requested) ? requested : 'pending'

  return (
    <>
      <PageHeader title="Explorer le catalogue" intro="Chercher une fiche, voir ce qui lui manque, la resynchroniser." />
      <div className="panel p-5"><GamesPanel filter={filter} onFilter={(next) => setParams({ filtre: next }, { replace: true })} /></div>
    </>
  )
}

export function AdminJobsPage() {
  return (
    <>
      <PageHeader title="Tâches" intro="Imports et synchronisations : l’historique, ce qui tourne, de quoi l’arrêter." />
      <div className="panel p-5"><JobsPanel /></div>
    </>
  )
}

export function AdminMembersPage() {
  return (
    <>
      <PageHeader title="Membres" intro="Les comptes, leurs rôles et les codes d’invitation." />
      <div className="panel p-5"><AdminMembers /></div>
    </>
  )
}

/* ── Communauté : comptes, collections et parties ───────────────────── */
export function AdminCommunityPage() {
  return (
    <WithStats>
      {({ users: u, sessions: s }) => {
        const col = u.collections
        return (
          <>
            <PageHeader title="Communauté" intro="Comptes, collections et parties enregistrées." />

            <Section id="comptes" title="Comptes et collections">
              <StatGrid>
                <StatCard label="Comptes" value={fmtNum(u.total)} />
                <StatCard label="Avec un compte BGG" value={fmtNum(u.with_bgg)} sub={u.total > 0 ? `${pct(u.with_bgg, u.total)} des comptes` : null} />
                <StatCard label="Sans compte BGG" value={fmtNum(u.without_bgg)} />
                <StatCard label="Administrateurs" value={fmtNum(u.admins)} />
              </StatGrid>
              <StatGrid>
                <StatCard label="Jeux en ludothèque (total)" value={fmtNum(col.total_links)} />
                <StatCard label="Jeux notés sur BGG" value={fmtNum(col.rated_links)} sub={col.total_links > 0 ? `${pct(col.rated_links, col.total_links)} des jeux` : null} />
                <StatCard label="Moyenne par membre" value={col.avg_per_user ?? '—'} sub="jeux" />
                <StatCard label="Plus grosse ludothèque" value={fmtNum(col.max_per_user)} sub="jeux" />
              </StatGrid>
            </Section>

            <Section id="parties" title="Parties enregistrées">
              <StatGrid>
                <StatCard label="Parties" value={fmtNum(s.total)} />
                <StatCard label="Ce mois-ci" value={fmtNum(s.this_month)} />
                <StatCard label="Cette semaine" value={fmtNum(s.this_week)} />
                <StatCard label="Cette année" value={fmtNum(s.this_year)} />
              </StatGrid>
              <StatGrid columns={3}>
                <StatCard label="Jeux différents joués" value={fmtNum(s.distinct_games)} />
                <StatCard label="Joueurs actifs" value={fmtNum(s.distinct_users)} />
                <StatCard label="Parties avec un avis" value={fmtNum(s.with_rating)} sub={s.total > 0 ? `${pct(s.with_rating, s.total)} des parties` : null} />
              </StatGrid>
              <p className="m-0 text-sm text-stone-700"><strong>Dernière partie enregistrée :</strong> {fmtDate(s.last_session)}</p>
            </Section>
          </>
        )
      }}
    </WithStats>
  )
}

/* ── Mappings : porte d'entrée vers les mécaniques et les thèmes ────── */
export function AdminMappingsPage() {
  return (
    <WithStats>
      {({ mappings: m, themes: th }) => (
        <>
          <PageHeader title="Mappings" intro="Comment les données de BGG deviennent des familles de jeu et des thèmes affichés aux membres." />

          <Section id="mecaniques" title="Mécaniques Engelstein" intro="Relie les mécaniques BGG aux familles du modèle d’Engelstein : elles pilotent les styles de jeu et les recommandations.">
            <StatGrid columns={3}>
              <StatCard label="Mappings actifs" value={fmtNum(m.total_mappings)} />
              <StatCard label="Familles utilisées" value={fmtNum(m.families_used)} />
              <StatCard label="Mécaniques BGG non mappées" value={fmtNum(m.unmapped_bgg_mechanics)} warn={Number(m.unmapped_bgg_mechanics) > 0}
                sub={Number(m.unmapped_bgg_mechanics) > 0 ? 'À mapper' : 'Tout est mappé'} to="/admin/mappings/mecaniques?tab=unmapped" />
            </StatGrid>
            <p className="m-0"><Link to="/admin/mappings/mecaniques" className="btn btn-primary">Gérer les mécaniques</Link></p>
          </Section>

          <Section id="themes" title="Thèmes" intro="Regroupe les catégories BGG en thèmes et types de jeu : ce sont les filtres de « À quoi on joue ».">
            <StatGrid>
              <StatCard label="Catégories BGG mappées" value={fmtNum(th.total_mappings)} />
              <StatCard label="Thèmes français" value={fmtNum(th.distinct_labels)} />
              <StatCard label="Groupes" value={fmtNum(th.distinct_groups)} />
              <StatCard label="Catégories non mappées" value={fmtNum(th.unmapped_bgg_categories)} warn={Number(th.unmapped_bgg_categories) > 0}
                sub={Number(th.unmapped_bgg_categories) > 0 ? 'Absentes des filtres' : 'Tout est mappé'} to="/admin/mappings/themes" />
            </StatGrid>
            <p className="m-0"><Link to="/admin/mappings/themes" className="btn btn-primary">Gérer les thèmes</Link></p>
          </Section>
        </>
      )}
    </WithStats>
  )
}
