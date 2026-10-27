# Rôles et permissions (phase 1)

Les rôles d'organisation sont portés par `organization_memberships.role` : une même personne peut être recruteur dans une organisation et jury dans une autre. Le rôle s'applique uniquement dans l'organisation active (`/app/{slug}`).

La correspondance rôle → permissions est dans `App\Enums\OrganizationRole::permissions()`. Les policies vérifient des permissions (`App\Enums\Permission`), jamais des noms de rôle. Chaque action métier appelle la policy elle-même : masquer un bouton ne suffit pas.

## Matrice

| Action | Superadmin | Orgadmin | Recruteur | Jury | Candidat |
| --- | --- | --- | --- | --- | --- |
| Panel `/admin` | ✅ | ❌ | ❌ | ❌ | ❌ |
| Panel `/app` de son organisation | ❌ (non membre) | ✅ | ✅ | ✅ | ❌ |
| Valider ou refuser une demande d'espace | ✅ | ❌ | ❌ | ❌ | ❌ |
| Créer, suspendre une organisation ; modifier slug et limites | ✅ | ❌ | ❌ | ❌ | ❌ |
| Modifier le profil et la page carrière | ✅ | ✅ | ❌ | ❌ | ❌ |
| Voir les membres | ❌ | ✅ | ❌ | ❌ | ❌ |
| Inviter, renvoyer, révoquer une invitation | ❌ | ✅ | ❌ | ❌ | ❌ |
| Changer un rôle, suspendre, retirer un membre | ❌ | ✅ (pas lui-même) | ❌ | ❌ | ❌ |
| Journal d'activité | Tout | Son organisation | ❌ | ❌ | ❌ |
| Utilisateurs (lecture) | ✅ | ❌ | ❌ | ❌ | ❌ |
| Horizon (files d'attente) | ✅ | ❌ | ❌ | ❌ | ❌ |
| Espace candidat | ❌ | ❌ | ❌ | ❌ | Le sien |

Les permissions des recruteurs et du jury sur les offres, candidatures et évaluations arrivent avec les phases 2 à 5.

## Règles métier

- Une organisation garde toujours au moins un orgadmin actif (rétrogradation, suspension et retrait refusés sinon).
- Personne ne gère sa propre adhésion (rôle, statut, retrait).
- Une organisation en attente ou suspendue est fermée à tous ses membres (404).
- Un membre suspendu n'accède plus à l'organisation ; son compte et ses autres adhésions restent intacts.
- Une invitation n'est acceptée que par un compte dont l'e-mail correspond. Un compte existant doit se connecter avant d'accepter.
- Un compte candidat ne peut pas rejoindre une organisation (comptes exclusifs, §9.1).

## Double authentification

Obligatoire pour les superadmins et pour toute personne orgadmin d'au moins une organisation (`User::requiresMultiFactorAuthentication()`), proposée aux autres. Deux méthodes : application TOTP (avec codes de secours) ou code par e-mail.
