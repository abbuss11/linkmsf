# LINK — consignes pour les assistants de code

- Référence fonctionnelle : `docs/cahier-des-charges.md`. Décisions : `docs/decisions.md`. Isolation : `docs/architecture.md`.
- Logique métier dans `app/Actions` ; chaque action vérifie l'autorisation (`Gate::forUser($actor)->authorize(...)`). Contrôleurs, Livewire et Filament ne font qu'appeler les actions.
- Toute table de données d'organisation : `organization_id` non nul + trait `BelongsToOrganization`. Requêtes inter-organisations uniquement via `queryAcrossOrganizations()` / `queryForOrganization()`, jamais `withoutGlobalScopes()`.
- Jobs touchant des données d'organisation : étendre `App\Jobs\OrganizationJob` et appeler `forOrganizationOf($model)`.
- Statuts, rôles et types : Enums dans `app/Enums`. Textes : `lang/fr`. Code en anglais.
- Chaque nouvelle ressource : un test d'accès depuis une autre organisation (`tests/Feature/Tenancy`).
- Avant de pousser : `vendor/bin/pint`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`.
