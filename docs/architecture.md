# Architecture : isolation multi-organisations

## Principe

Base MySQL partagée. Chaque table de données d'organisation porte `organization_id` (non nul, indexé, clé étrangère) et son modèle utilise le trait `App\Models\Concerns\BelongsToOrganization`.

Le trait :

1. ajoute le scope global `OrganizationScope`, qui filtre sur l'organisation active ;
2. remplit `organization_id` à la création à partir de l'organisation active (exception s'il n'y en a pas et qu'aucune n'est fournie) ;
3. interdit de changer `organization_id` d'une ligne existante.

**Sans organisation active, une requête ne renvoie rien** (`where 1 = 0`). Le code qui doit traverser les organisations le dit explicitement avec `Model::queryAcrossOrganizations()` ou `Model::queryForOrganization($org)`. Ces appels sont faciles à rechercher lors d'une revue.

## Organisation active

`App\Support\Tenancy\OrganizationContext` est un singleton *scoped* : il est remis à zéro à chaque requête HTTP et à chaque job. Il n'est jamais rempli à partir d'une donnée envoyée par le client :

| Contexte | Qui définit l'organisation |
| --- | --- |
| Panel `/app/{slug}` | `SetOrganizationContext`, après que Filament a identifié le tenant et vérifié `canAccessTenant` (y compris sur les requêtes Livewire) |
| Job en file d'attente | `App\Jobs\OrganizationJob` : l'id vient du modèle traité à la création du job, puis le middleware `RestoreOrganizationContext` le restaure avant `handle()` |
| Lien public (invitation) | Aucun : l'invitation est retrouvée par son ULID et son jeton secret, via `queryAcrossOrganizations()` |
| Panel `/admin` | Aucun : les vues plateforme utilisent explicitement les requêtes inter-organisations |

Filament ajoute son propre scope de tenant (`app_tenancy`) sur les modèles des ressources du panel `/app`, et rattache au tenant courant tout enregistrement de ces modèles créé pendant une requête du panel. Les deux mécanismes se cumulent ; `queryAcrossOrganizations()` retire les deux scopes.

## Formulaires dynamiques (phase 2)

- `application_forms.schema` : copie de travail éditée dans l'assistant.
- `application_form_versions` : copies figées, immuables, créées à la publication si l'empreinte du schéma change. Chaque candidature (phase 3) référencera sa version.
- `app/Forms/Dynamic` : `SchemaValidator` (structure), `RulesBuilder` (règles de validation serveur), `FilamentFormRenderer` (affichage), `RequirementRuleValidator` (critères éliminatoires). L'aperçu et le futur formulaire candidat utilisent les mêmes règles.

## Exceptions documentées

- `organizations` : c'est le tenant lui-même.
- `organization_requests` : l'organisation n'existe pas encore.
- `users` : le compte est global ; l'accès est décidé par les adhésions.
- `audit_logs` : `organization_id` est nul pour les événements plateforme. Le modèle n'utilise donc pas le trait ; la ressource `/app` filtre explicitement sur le tenant et `AuditLogPolicy` revérifie.

## Tests

`tests/Feature/Tenancy` vérifie le trait (lecture, création, réaffectation, jobs) et, pour chaque ressource, l'accès depuis une autre organisation : URL, identifiant, action Livewire et action métier.
