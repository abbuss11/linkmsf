# LINK

Plateforme SaaS de recrutement multi-organisations : publication d'offres, formulaires de candidature par offre, ATS, évaluation par jury et décision. La référence fonctionnelle est le [cahier des charges](docs/cahier-des-charges.md).

État : **phase 1 (fondations)**. Organisations, invitations, rôles, isolation multi-tenant, authentification interne et candidat, journal d'audit.

## Stack

| Couche | Version |
| --- | --- |
| PHP | 8.3 (plateforme figée dans `composer.json`) |
| Laravel | 13 |
| Filament | 5 (Livewire 4) : panels `/admin` et `/app/{organisation}` |
| Base de données | MySQL 8 (utf8mb4) |
| Files d'attente | Redis + Horizon |
| Front public | Blade + Tailwind 4 (Vite), sans JavaScript obligatoire |
| Audit | spatie/laravel-activitylog 4 (table `audit_logs`) |
| Tests et qualité | Pest 4, Larastan 3 (niveau 6), Pint |

Les versions exactes sont figées dans `composer.lock` et `package-lock.json`.

## Installation locale

Prérequis : PHP 8.3 avec `pdo_mysql`, `redis`, `intl`, `mbstring`, `zip` ; Composer 2 ; Node 22 ; MySQL 8 ; Redis ; un serveur SMTP de développement ([Mailpit](https://mailpit.axllent.org/)) ou `MAIL_MAILER=log`.

```bash
git clone https://github.com/abbuss11/linkmsf.git link && cd link
cp .env.example .env               # puis renseigner DB_PASSWORD, etc.
composer install
php artisan key:generate
php artisan migrate --seed         # données de démonstration
php artisan storage:link
npm ci && npm run build

# trois terminaux
php artisan serve                  # http://localhost:8000
php artisan horizon                # ou : php artisan queue:work
# Mailpit : http://localhost:8025
```

`APP_URL` doit correspondre à l'adresse réellement utilisée : les liens d'invitation et de vérification sont signés avec elle.

## Comptes de démonstration

Mot de passe pour tous : `password`. La double authentification par e-mail est déjà activée pour les comptes qui l'exigent : le code arrive dans Mailpit (ou dans `storage/logs/laravel.log` avec `MAIL_MAILER=log`).

| Compte | Rôle | Accès |
| --- | --- | --- |
| `admin@link.test` | Superadmin | `/admin` |
| `admin@sahel-avenir.test` | Orgadmin, Association Sahel Avenir | `/app/sahel-avenir` |
| `recruteur@sahel-avenir.test` | Recruteur, Sahel Avenir | `/app/sahel-avenir` |
| `jury@sahel-avenir.test` | Jury, Sahel Avenir | `/app/sahel-avenir` |
| `admin@kawtal-conseil.test` | Orgadmin, Cabinet Kawtal Conseil | `/app/kawtal-conseil` |
| `recruteur@kawtal-conseil.test` | Recruteur, Kawtal | `/app/kawtal-conseil` |
| `jury@kawtal-conseil.test` | Jury, Kawtal | `/app/kawtal-conseil` |
| `multi@link.test` | Recruteur chez Sahel, jury chez Kawtal | les deux |
| `candidat@link.test` | Candidat | `/connexion`, `/espace-candidat` |

Une demande d'espace en attente (« Coopérative Tafadek ») est aussi créée.

## Commandes utiles

```bash
vendor/bin/pest                    # tests (base MySQL link_testing)
vendor/bin/pint                    # formatage
vendor/bin/phpstan analyse         # analyse statique
```

Les tests utilisent la base `link_testing` (voir `phpunit.xml`) avec les identifiants du `.env`.

## Organisation du code

- `app/Actions` : une classe par opération métier ; les contrôleurs et pages Filament ne font qu'appeler ces actions. Chaque action vérifie elle-même l'autorisation.
- `app/Enums` : rôles, statuts, permissions, événements d'audit.
- `app/Models/Concerns/BelongsToOrganization.php` : isolation par organisation (voir [docs/architecture.md](docs/architecture.md)).
- `app/Policies` : une policy par modèle exposé.
- `app/Filament/Admin` (panel plateforme) et `app/Filament/App` (panel organisation).
- `lang/fr` : tous les textes d'interface.

Voir aussi [docs/roles-et-permissions.md](docs/roles-et-permissions.md) et [docs/decisions.md](docs/decisions.md).
