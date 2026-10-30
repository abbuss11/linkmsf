# LINK — Cahier des charges

Oct 27, 2026 · @Abba

LINK est une plateforme SaaS de recrutement multi-organisations : chaque organisation publie ses offres, reçoit des candidatures via des formulaires générés pour chaque offre, puis les traite dans un ATS jusqu'à la décision finale.

## 1. Présentation et décisions de cadrage

### 1.1 Contexte

Les recrutements et appels à candidatures se gèrent souvent avec plusieurs outils séparés : un site pour publier, des emails ou WhatsApp pour recevoir les dossiers, des tableurs pour trier. Cette dispersion fait perdre des dossiers, rend le suivi difficile et ne laisse aucune trace des décisions. LINK centralise tout le cycle dans une plateforme unique, configurable et sécurisée.

### 1.2 Objectifs

LINK doit permettre à une organisation de réaliser un recrutement complet sans traitement manuel hors plateforme :

**Créer l'organisation → inviter l'équipe → publier une offre → définir les critères → générer le formulaire → recevoir les candidatures → vérifier la recevabilité → filtrer et évaluer → constituer la shortlist → conduire les entretiens → décider → informer les candidats → consulter les rapports.**

En parallèle, un candidat crée un seul compte, postule à des offres de plusieurs organisations, dépose ses pièces, reçoit une confirmation et suit ses candidatures.

### 1.3 Périmètre

**Inclus :** plateforme web responsive (ordinateur, tablette, mobile), back-office des organisations, back-office plateforme, vitrine publique, espace candidat, ATS, évaluations, notifications email et in-app, assistance IA, rapports.

**Hors périmètre initial :** application mobile native, signature électronique certifiée, intégration avec des systèmes publics externes, facturation automatisée des abonnements. Ces extensions pourront venir dans une version ultérieure.

### 1.4 Décisions de cadrage validées

| Sujet | Décision |
| --- | --- |
| Modèle | SaaS multi-organisations, une base MySQL partagée, isolation par `organization_id` |
| Stack | Laravel + Filament (back-offices) + Blade/Livewire + Tailwind (vitrine et espace candidat) + MySQL |
| Compte candidat | Global : un seul compte et un seul profil pour postuler à toutes les organisations |
| Vitrine publique | Job board commun à toutes les organisations + page carrière propre à chaque organisation |
| Langue | Français au lancement, textes prévus pour la traduction (fichiers `lang/`) |
| Canal de notification principal | Email + notifications in-app ; WhatsApp en extension (voir section 8) |
| Contexte d'usage | Majorité de candidats sur smartphone avec connexion lente (Niger et Afrique de l'Ouest) |

## 2. Utilisateurs, rôles et isolation

### 2.1 Rôles

Un même utilisateur peut appartenir à plusieurs organisations avec un rôle différent dans chacune (table `organization_memberships`). Les rôles d'organisation s'appliquent uniquement dans l'organisation active.

| Rôle | Portée | Peut | Ne peut pas |
| --- | --- | --- | --- |
| Superadmin | Plateforme | Gérer les organisations (créer, valider, suspendre), les paramètres globaux, les signalements ; voir les indicateurs globaux, les jobs en échec et le journal d'audit | Ouvrir un dossier de candidature sans motif saisi et journalisé (accès « bris de glace ») |
| Orgadmin | Organisation | Modifier le profil et la page carrière, inviter et retirer des membres, attribuer les rôles, voir toute l'activité de l'organisation, gérer modèles de messages et paramètres | Agir sur une autre organisation |
| Recruteur | Offres assignées (ou toutes, selon paramètre) | Créer et publier des offres, définir critères et formulaires, traiter les candidatures, gérer pipeline et shortlist, planifier entretiens, communiquer, exporter | Gérer les membres ; prendre une décision finale si l'organisation l'exige d'un orgadmin |
| Jury | Dossiers attribués | Consulter les dossiers attribués, noter selon la grille, commenter, participer à la délibération | Voir les dossiers non attribués, voir les notes des autres avant d'avoir soumis la sienne, changer un statut, contacter un candidat |
| Candidat | Son compte | Gérer son profil global, postuler, suivre ses candidatures, retirer une candidature, demander l'export ou la suppression de ses données | Voir quoi que ce soit d'autre |

Les permissions sont vérifiées côté serveur (Policies, Gates, middleware). Masquer un bouton ne protège rien.

### 2.2 Isolation multi-organisations

&#91;embedded content: architecture · 3 espaces, 1 base partagée\]

Le candidat garde un profil global ; tout le reste est rangé par organisation et filtré automatiquement.

- Toute table de données d'organisation porte `organization_id` (non nul, indexé, clé étrangère).
- Un trait `BelongsToOrganization` ajoute un global scope sur l'organisation active et remplit `organization_id` à la création ; il est appliqué à tous ces modèles.
- Le back-office utilise la gestion native des tenants de Filament (`->tenant(Organization::class)`, URL `/app/{organisation}`) ; changer d'organisation passe par le sélecteur de tenant.
- Les routes, jobs, exports, notifications et téléchargements de fichiers récupèrent l'organisation depuis l'objet concerné, jamais depuis un paramètre envoyé par le client.
- Les jobs en file d'attente portent l'`organization_id` et le restaurent avant traitement.
- Les données du candidat appartiennent au candidat ; une organisation n'y accède qu'au travers des candidatures déposées sur ses offres (voir 4.3).
- Chaque ressource a un test automatisé qui tente un accès depuis une autre organisation (URL, identifiant, export, fichier) et vérifie un refus 403 ou 404.

## 3. Offres et formulaires dynamiques

### 3.1 Création d'une offre

Assistant en cinq étapes dans Filament (composant Wizard), avec brouillon enregistré à chaque étape.

1. **Informations générales** — type (emploi, stage, bourse, programme, consultance, appel à projets), titre, référence, description, missions, lieu, type de contrat, niveau d'études, expérience, compétences, dates de publication et de clôture, nombre de places.
2. **Critères** — chaque critère a un usage : *éliminatoire*, *obligatoire*, *souhaité*, *évalué par score* ou *informatif*. Un critère éliminatoire est relié à un champ du formulaire et à une règle (ex. âge entre 18 et 35, niveau ≥ Licence, réponse = Oui).
3. **Paramètres de candidature** — pièces à fournir, formats et tailles autorisés, nombre de fichiers par champ, modification après dépôt (non / jusqu'à la clôture), règle de doublon (une candidature par offre / remplacement autorisé), instructions, message de confirmation, statuts visibles par le candidat, notifications.
4. **Formulaire** — construit avec le générateur (3.3), prévisualisable tel que le candidat le verra, y compris sur mobile.
5. **Vérification et publication** — récapitulatif, contrôle des incohérences (date de clôture passée, critère éliminatoire sans champ, formulaire vide), puis publier ou soumettre à validation.

Une offre peut être **dupliquée** avec ses critères, son formulaire, son pipeline et sa grille d'évaluation, pour relancer un appel.

### 3.2 Cycle de vie d'une offre

Brouillon → En attente de validation (si activé par l'organisation) → Publiée → Suspendue ↔ Publiée → Clôturée → Archivée.

- La clôture est automatique à la date limite (tâche planifiée toutes les minutes), avec le fuseau horaire de l'organisation (`Africa/Niamey` par défaut).
- Une offre clôturée refuse toute nouvelle soumission, y compris un formulaire ouvert avant l'heure limite et envoyé après. Seul un recruteur autorisé peut la rouvrir, avec motif journalisé.

### 3.3 Générateur de formulaires

**Types de champs :** texte court, texte long (limite de mots), email, téléphone (format international, +227 par défaut), nombre, date, liste déroulante, choix unique, choix multiple, case à cocher, oui/non, URL, fichier, section et texte d'aide (sans saisie).

**Chaque champ définit :** libellé, clé technique stable (générée, non modifiable après publication), aide, type, obligatoire ou non, ordre, options, règles de validation (longueur, min/max, format), formats et taille de fichier, condition d'affichage (« afficher si le champ X vaut Y »).

**Champs du profil :** les champs liés au profil global (nom, téléphone, ville, diplôme, langues…) sont marqués comme tels et pré-remplis ; le candidat peut les corriger, et la correction met à jour son profil s'il le confirme.

### 3.4 Versionnement

- Publier un formulaire crée une version figée (`application_form_versions`), avec le schéma complet des champs en JSON.
- Modifier le formulaire d'une offre publiée crée une nouvelle version ; les candidatures déjà soumises gardent la leur.
- Chaque candidature référence la version utilisée, et ses réponses sont lues avec ce schéma : un champ supprimé ensuite reste lisible sur les anciens dossiers.
- Modifier un champ éliminatoire sur une offre qui a déjà des candidatures affiche un avertissement et ne recalcule jamais la recevabilité des dossiers existants sans action explicite.

### 3.5 Validation

Toute validation est refaite côté serveur, même si l'interface valide en direct : champs obligatoires, formats, options autorisées, limites, type MIME réel et taille des fichiers, dates d'ouverture et de clôture, droit du candidat à postuler, règle de doublon. Les messages d'erreur sont en français clair et affichés sous le champ concerné.

## 4. Vitrine publique et espace candidat

### 4.1 Vitrine publique

| Page | URL | Contenu |
| --- | --- | --- |
| Job board commun | `/offres` | Toutes les offres publiées des organisations actives ; recherche par mot-clé ; filtres type, organisation, lieu, niveau, date limite ; tri par date ou clôture proche |
| Page carrière | `/o/{slug-organisation}` | Logo, couleurs, présentation de l'organisation et ses offres ouvertes ; l'organisation peut choisir de ne pas apparaître dans le job board commun |
| Détail d'une offre | `/offres/{slug-offre}` | Description, critères, pièces demandées, date limite avec compte à rebours, bouton « Postuler » |

- Balises Open Graph et image de partage générée pour WhatsApp, Facebook et LinkedIn.
- Paramètre `?source=` conservé jusqu'à la soumission pour mesurer les canaux de diffusion.
- Pages publiques légères (cible : moins de 200 Ko hors images), lisibles sans JavaScript, mises en cache.
- Données structurées `JobPosting` (schema.org) pour le référencement dans Google Jobs.

### 4.2 Compte candidat global

- Inscription, connexion, déconnexion, vérification de l'email, mot de passe oublié, sessions sécurisées. Connexion Google en extension.
- Un seul profil global : identité, coordonnées, ville et pays, niveau d'études, formations, expériences, compétences, langues, CV par défaut, documents réutilisables (diplômes, attestations).
- Le candidat voit toutes ses candidatures, toutes organisations confondues.

### 4.3 Ce qu'une organisation voit d'un candidat

Une organisation ne consulte **jamais** le profil global en direct. À la soumission, LINK enregistre une **copie figée** (snapshot) des informations de profil et des documents transmis dans la candidature. L'organisation ne voit que ce snapshot, uniquement pour les candidatures déposées sur ses offres. Un candidat qui modifie son profil plus tard ne modifie pas les dossiers déjà envoyés.

### 4.4 Parcours de candidature

1. Le candidat ouvre une offre et clique sur « Postuler ».
2. S'il n'est pas connecté, il crée un compte ou se connecte, puis revient automatiquement sur l'offre.
3. LINK vérifie qu'il n'a pas déjà postulé (selon la règle de doublon de l'offre).
4. Formulaire en étapes : profil pré-rempli → questions de l'offre → documents → récapitulatif.
5. Brouillon enregistré automatiquement ; le candidat peut reprendre plus tard, sur un autre appareil.
6. Consentement explicite au traitement des données par l'organisation, puis soumission.
7. Validation serveur, enregistrement dans une transaction, référence lisible générée (ex. `LNK-2026-ORG-000123`).
8. Page et email de confirmation ; la candidature apparaît dans « Mes candidatures ».

### 4.5 Après la soumission

- Le candidat voit uniquement les **statuts publics** configurés par l'offre (ex. Reçue, En cours d'examen, Convoqué(e), Retenu(e), Non retenu(e)), jamais les étapes internes ni les notes.
- Modification possible seulement si l'offre l'autorise et avant la clôture ; chaque modification est historisée.
- Le candidat peut retirer sa candidature tant que la décision n'est pas prise.
- Rappel automatique 48 h avant la clôture pour un brouillon non soumis.

### 4.6 Contraintes mobiles et réseau

- Formulaire utilisable sur un smartphone Android d'entrée de gamme en 3G.
- Upload fichier par fichier avec barre de progression et nouvelle tentative en cas de coupure ; un fichier déjà envoyé n'est pas renvoyé.
- Aucune perte de saisie si la connexion tombe : les réponses sont sauvegardées à chaque étape.

## 5. ATS : traitement des candidatures

### 5.1 Tableau des candidatures

Par offre, une table Filament paginée côté serveur avec colonnes configurables : référence, nom, date de soumission, étape, recevabilité, complétude (pièces reçues / attendues), score IA, note moyenne du jury, tags, évaluateurs assignés, prochaine action. Une vue Kanban (une colonne par étape, glisser-déposer) complète la vue tableau.

### 5.2 Recherche, filtres et tri

- Recherche plein texte (index FULLTEXT MySQL) sur le nom, les réponses texte et le texte extrait des CV.
- Filtres combinables : étape, recevabilité, date de soumission, niveau d'études, années d'expérience, compétences, langues, ville, réponses aux champs structurés (générés automatiquement à partir du formulaire de l'offre), pièces manquantes, complétude, score IA (plage), note moyenne (plage), évalué ou non, évaluateur, tags.
- Tri stable : score, note, date, nom.
- Filtres enregistrables et partageables dans l'équipe (« Licence+, Niamey, score ≥ 60 »).
- Les filtres et la recherche portent sur toutes les données enregistrées, pas seulement sur la page affichée.

### 5.3 Recevabilité automatique

À la soumission, un job applique les critères éliminatoires de la version du formulaire. Résultat enregistré par critère (satisfait / non satisfait / non vérifiable) avec la valeur fournie. Un dossier non recevable va dans l'étape « Non recevable » mais reste consultable, et un recruteur peut le réintégrer avec motif.

### 5.4 Pipeline

Pipeline par défaut, modifiable par organisation puis par offre :

1. Reçue
2. Vérification de complétude
3. Recevabilité
4. Présélection
5. Test ou évaluation technique
6. Entretien
7. Délibération
8. Retenu(e)

Étapes de sortie accessibles selon les transitions autorisées : Non recevable, Non retenu(e), Liste d'attente, Retirée (par le candidat).

- Les transitions autorisées sont configurées (une machine à états par offre) ; une transition non prévue est refusée côté serveur.
- Chaque changement enregistre auteur, date, ancienne et nouvelle étape, motif (obligatoire pour une sortie) et commentaire.
- Actions groupées sur une sélection : changer d'étape, ajouter un tag, assigner au jury, envoyer un message, exporter.
- Chaque étape peut déclencher un message automatique au candidat (désactivable).

### 5.5 Fiche candidature

Snapshot du profil, réponses (lues selon la version du formulaire), visionneuse PDF intégrée pour les pièces, résultats de recevabilité, analyse IA, évaluations, notes internes privées ou partagées, historique complet, messages envoyés, entretiens.

### 5.6 Listes longues, shortlist et liste d'attente

- Créer plusieurs listes par offre (liste longue, shortlist, liste d'attente), ajouter ou retirer un candidat avec motif.
- Classement par note moyenne pondérée, puis score IA ; ordre manuel possible et journalisé.
- Comparaison côte à côte de 2 à 4 candidats.
- Export de la liste (Excel, PDF) et des pièces des candidats (ZIP), selon les permissions.
- La présélection automatique (ex. « les 50 meilleurs scores ») n'existe que si l'organisation l'active ; elle propose une liste qu'un humain valide.

## 6. Évaluation, jury et entretiens

### 6.1 Grilles d'évaluation

- Une grille par étape évaluée (présélection, test, entretien), modèles réutilisables au niveau de l'organisation.
- Chaque critère : nom, description, consignes pour l'évaluateur, barème (ex. 1 à 5 ou 0 à 20), pondération, seuil éventuel.
- Note totale = somme pondérée ramenée sur 100 ; la formule est affichée dans la grille et couverte par des tests unitaires.
- Une grille utilisée par au moins une évaluation ne se modifie plus : on en crée une nouvelle version.

### 6.2 Attribution au jury

- Attribution manuelle ou automatique (répartition équilibrée, N évaluateurs par dossier).
- Un membre du jury déclare un conflit d'intérêts sur un dossier ; il en est retiré et le dossier est réattribué.
- Option **évaluation anonyme** par offre : nom, photo, contacts, genre et âge masqués pour le jury.

### 6.3 Évaluation

- Le jury voit uniquement ses dossiers attribués et la grille.
- **Évaluation à l'aveugle :** les notes des autres évaluateurs ne sont visibles qu'après avoir soumis la sienne.
- Recommandation finale par évaluateur : Très favorable, Favorable, Réservé, Défavorable.
- Une évaluation soumise ne se modifie plus sans réouverture par un recruteur (journalisée).
- Synthèse par dossier : moyenne, écart-type, écart maximal ; un écart de plus de 25 points sur 100 entre deux évaluateurs est signalé pour délibération.
- Suivi d'avancement : « 34 / 120 évaluations faites », relance des évaluateurs en retard.

### 6.4 Tests

Un test peut être passé hors de LINK : le recruteur enregistre le test (type, date, statut, score, fichier de résultat, évaluateur). Les tests en ligne intégrés (QCM chronométré) sont une extension.

### 6.5 Entretiens

- Planifier un entretien : date, heure, durée, lieu ou lien visio, membres du jury.
- Convocation automatique au candidat et invitation calendrier (.ics) aux participants.
- Le candidat peut confirmer ou demander un report depuis son espace.
- Statuts : Planifié, Confirmé, Réalisé, Absent, Annulé, Reporté.

### 6.6 Délibération et décision

- Vue de délibération : classement, synthèse des notes, recommandations, écarts signalés.
- Décision finale (Retenu(e), Liste d'attente, Non retenu(e)) avec motif, prise par un rôle autorisé.
- Procès-verbal de sélection en PDF : offre, critères, membres du jury, classement, décisions, date.

## 7. Import de dossiers et assistance IA

### 7.1 Import de dossiers

Cas d'usage : une organisation a reçu des candidatures par email ou sur papier et veut les traiter dans LINK.

- Import d'un fichier Excel/CSV (une ligne par candidat, colonnes associées aux champs du formulaire) et/ou d'un lot de CV (PDF, DOCX, ZIP).
- Contrôle des formats et tailles, rapport ligne par ligne (importé, ignoré, erreur avec raison).
- Détection des doublons potentiels par email, téléphone et nom.
- Les candidatures importées portent la source « Import » et l'auteur de l'import ; elles n'ont pas de compte candidat associé tant que la personne n'en crée pas un avec le même email.
- Traitement en file d'attente avec suivi de progression.

### 7.2 Extraction de CV

- Extraction du texte des PDF et DOCX ; OCR pour les PDF scannés en extension.
- Structuration via un service `CvParser` derrière une interface (fournisseur interchangeable) : identité, expériences, formations, compétences, langues, certifications.
- Les données extraites sont stockées séparément des données déclarées par le candidat, marquées « extraites, à vérifier », et corrigeables par le recruteur.

### 7.3 Assistance IA

| Fonction | Ce qu'elle produit |
| --- | --- |
| Résumé de dossier | 3 à 5 phrases sur le parcours, rattachées aux passages du CV |
| Correspondance aux critères | Pour chaque critère publié : satisfait / partiel / absent / incertain, avec l'élément du dossier qui le justifie |
| Score de correspondance | Indice de 0 à 100 calculé à partir des critères et de leurs pondérations visibles |
| Pièces manquantes | Liste des documents demandés absents ou illisibles |
| Questions d'entretien | 5 à 10 questions liées aux exigences du poste et aux manques du dossier |
| Synthèse du jury | Résumé des commentaires des évaluateurs pour la délibération |

### 7.4 Règles obligatoires pour l'IA

- L'IA assiste, elle ne décide pas : aucun changement d'étape, rejet ou classement final sans action humaine.
- Chaque résultat cite les éléments du dossier qui le justifient ; une donnée absente est signalée « incertaine », jamais inventée.
- Le genre, l'âge, la nationalité, la religion, l'origine, la situation familiale, la photo et le nom ne sont pas transmis au modèle pour le score.
- Le score est affiché comme « indice de correspondance aux critères », jamais comme une valeur de la personne.
- Chaque analyse enregistre fournisseur, modèle, version du prompt, date et coût (`ai_analysis_results`).
- L'organisation active ou désactive l'IA ; les candidats en sont informés dans la mention de consentement.
- Pas d'envoi de données à un fournisseur externe sans contrat et garanties de protection validés (voir section 12).
- Échec de l'API = statut « analyse en attente » avec nouvelle tentative ; une candidature n'est jamais bloquée par l'IA.
- Avant activation en production, le score est évalué sur un échantillon de dossiers déjà jugés par des recruteurs, et les écarts sont documentés.

## 8. Notifications, tableaux de bord et rapports

### 8.1 Notifications

**Canaux :** email (principal) et notifications in-app au lancement. WhatsApp Business et SMS en extension, via un système de canaux interchangeables (Laravel Notifications) pour les ajouter sans réécrire les envois.

| Destinataire | Événements |
| --- | --- |
| Candidat | Création de compte, vérification email, confirmation de soumission (avec référence), brouillon non soumis 48 h avant clôture, changement de statut public, demande de complément, convocation à un entretien ou test, décision finale |
| Recruteur | Nouvelle candidature (résumé quotidien optionnel), dossier incomplet, offre qui ferme dans 48 h, écart de notation signalé, import terminé ou échoué |
| Jury | Dossiers attribués, rappel d'évaluation en retard, entretien planifié |
| Orgadmin | Invitation acceptée, quota ou limite atteinte |
| Superadmin | Nouvelle organisation à valider, jobs en échec, signalement d'une offre |

- Modèles de messages modifiables par organisation, avec variables (`{{prenom}}`, `{{offre}}`, `{{organisation}}`, `{{reference}}`, `{{date_entretien}}`, `{{lien}}`).
- Envoi de masse sur une sélection : aperçu, confirmation du nombre de destinataires, envoi en file d'attente.
- Chaque envoi est journalisé (statut, date, erreur) ; un envoi en échec peut être relancé.
- Le candidat choisit ses préférences de notification non essentielles.

### 8.2 Tableaux de bord

| Rôle | Contenu |
| --- | --- |
| Superadmin | Organisations (actives, en attente, suspendues), utilisateurs, offres publiées, candidatures par période, jobs en échec, signalements |
| Orgadmin | Offres actives, candidatures reçues, dossiers à traiter, progression par étape, échéances, activité de l'équipe |
| Recruteur | À traiter aujourd'hui (non lus, évaluations en retard, entretiens de la semaine, offres qui ferment), puis ses offres et leur progression |
| Jury | Dossiers à évaluer, entretiens à venir |
| Candidat | Candidatures et statuts, brouillons, entretiens, notifications |

### 8.3 Rapports

- Par offre : candidatures par jour, par source, entonnoir par étape avec taux de passage, motifs de non-recevabilité, délais (dépôt → première action, dépôt → décision), répartition par genre, ville et niveau d'études (pour suivre la diversité, jamais pour trier).
- Exports : liste filtrée (CSV, Excel), procès-verbal de sélection (PDF), journal d'activité d'une offre (CSV).
- Les exports respectent les permissions et l'anonymat éventuel, et sont eux-mêmes journalisés.

## 9. Modèle de données et architecture technique

### 9.1 Modèle de données

La colonne « Org » indique les tables isolées par `organization_id`.

| Table | Org | Rôle et champs clés |
| --- | --- | --- |
| `users` | — | Comptes (email, mot de passe, type : interne / candidat, 2FA) |
| `organizations` | — | Nom, slug, logo, couleurs, statut (en attente, active, suspendue), fuseau horaire, paramètres (JSON), visible dans le job board |
| `organization_memberships` | ✔ | user, rôle (orgadmin, recruteur, jury), statut, invité par |
| `invitations` | ✔ | email, rôle, jeton, expiration |
| `candidate_profiles` | — | Profil global du candidat (1 par user) : identité, coordonnées, études, compétences, langues |
| `candidate_experiences`, `candidate_educations` | — | Parcours du candidat |
| `candidate_documents` | — | Documents réutilisables du candidat (CV, diplômes) |
| `job_postings` | ✔ | Offre : type, titre, slug, référence, description, lieu, dates, statut, paramètres (JSON), créée par |
| `job_requirements` | ✔ | Critère : libellé, usage (éliminatoire, obligatoire, souhaité, score, informatif), champ lié, règle (JSON), pondération |
| `application_forms` | ✔ | Formulaire de l'offre, version courante en brouillon |
| `application_form_versions` | ✔ | Version publiée figée : numéro, schéma des champs (JSON), publiée le |
| `pipelines`, `pipeline_stages`, `pipeline_transitions` | ✔ | Étapes, ordre, type, statut public associé, transitions autorisées |
| `applications` | ✔ | offre, candidat (nullable si import), version du formulaire, référence, étape, statut public, recevable, complétude, snapshot du profil (JSON), source, soumise le |
| `application_answers` | ✔ | Réponse par clé de champ (valeur texte et valeur JSON), indexée pour les filtres |
| `application_documents` | ✔ | Métadonnées fichier : type, nom d'origine, chemin privé, MIME vérifié, taille, empreinte SHA-256, texte extrait |
| `application_status_histories` | ✔ | Ancienne et nouvelle étape, auteur, motif, commentaire, date |
| `eligibility_results` | ✔ | Résultat par critère éliminatoire avec la valeur évaluée |
| `shortlists`, `shortlist_items` | ✔ | Listes et candidats, position, motif |
| `scorecards`, `scorecard_versions` | ✔ | Grilles et leurs versions (critères, barèmes, pondérations) |
| `jury_assignments` | ✔ | Dossier, évaluateur, étape, conflit déclaré |
| `evaluations` | ✔ | Notes par critère (JSON), total, recommandation, commentaire, soumise le |
| `assessments` | ✔ | Tests : type, date, statut, score, fichier, évaluateur |
| `interviews`, `interview_participants` | ✔ | Entretiens et jury |
| `notes` | ✔ | Notes internes, privées ou partagées |
| `message_templates`, `messages` | ✔ | Modèles et envois, statut de livraison |
| `saved_filters` | ✔ | Filtres enregistrés |
| `imports`, `import_rows` | ✔ | Suivi des imports ligne par ligne |
| `ai_analysis_results` | ✔ | Type d'analyse, résultat (JSON), justifications, fournisseur, modèle, version de prompt, coût |
| `audit_logs` | ✔ / — | Acteur, action, objet, avant/après, IP, date |

**Contraintes clés :** clés étrangères partout ; index uniques (`organization_id`, `slug`) pour les offres et (`job_posting_id`, `candidate_user_id`) quand la règle « une candidature par offre » s'applique ; index composites commençant par `organization_id` ; index FULLTEXT sur `application_documents.extracted_text` et les réponses texte ; suppressions douces (`softDeletes`) sur offres et candidatures.

### 9.2 Stack et versions

| Couche | Choix |
| --- | --- |
| Framework | Laravel, dernière version stable (12 ou supérieure), PHP 8.3+ |
| Back-offices | Filament (dernière version stable) : panel `/admin` (superadmin) et panel `/app` multi-tenant (organisations) |
| Vitrine et espace candidat | Blade + Livewire 3 + Tailwind CSS + Alpine.js ; composants Filament Forms réutilisés pour le rendu des formulaires dynamiques |
| Base de données | MySQL 8 (InnoDB, utf8mb4) |
| Files d'attente | Laravel Queues sur Redis, supervisées par Horizon |
| Stockage | Disque local privé en développement, stockage objet S3-compatible privé en production |
| Permissions | Policies Laravel + spatie/laravel-permission (rôles par équipe) |
| Audit | spatie/laravel-activitylog |
| PDF | Génération côté serveur (ex. Browsershot ou DomPDF) |
| Tests et qualité | Pest, Larastan niveau 6+, Laravel Pint, CI GitHub Actions |

Les versions exactes sont figées au démarrage (`composer.lock`, `package-lock.json`) et documentées dans le README.

### 9.3 Organisation du code

- `app/Actions` : une classe par opération métier (SubmitApplication, MoveApplicationToStage, PublishJobPosting…). Les contrôleurs, composants Livewire et ressources Filament appellent ces actions.
- `app/Enums` : statuts, types de champs, usages de critères, rôles.
- `app/Models/Concerns/BelongsToOrganization` : isolation.
- `app/Policies` : une policy par modèle exposé.
- `app/Jobs`, `app/Notifications`, `app/Services/Ai` (interfaces `CvParser`, `CvAnalyzer`).
- `app/Forms/Dynamic` : moteur de formulaires (schéma → composants, schéma → règles de validation).
- Transactions pour la soumission d'une candidature, les changements d'étape et les décisions.

## 10. Sécurité, données personnelles et exigences non fonctionnelles

### 10.1 Sécurité

- Mots de passe hachés (bcrypt/argon2), 2FA obligatoire pour superadmin et orgadmin, recommandée pour les recruteurs.
- CSRF, validation de toutes les entrées, requêtes Eloquent paramétrées, échappement systématique dans les vues.
- Autorisation côté serveur sur chaque route, action Livewire, ressource Filament, export et téléchargement.
- Fichiers stockés hors de la racine publique, renommés (UUID), servis uniquement via une route autorisée et une URL signée de courte durée ; type MIME réel vérifié ; antivirus (ClamAV) en extension.
- Limitation de débit : connexion, inscription, soumission, réinitialisation de mot de passe, exports.
- Anti-spam sur les formulaires publics (honeypot + Cloudflare Turnstile).
- En-têtes de sécurité (CSP, HSTS, X-Frame-Options), cookies `Secure`, `HttpOnly`, `SameSite`.
- Secrets uniquement dans l'environnement, jamais dans le dépôt ; audit des dépendances dans la CI.
- Erreurs génériques côté utilisateur ; journaux sans mots de passe, jetons, ni contenu de CV.

### 10.2 Données personnelles

- Consentement explicite au traitement à la soumission, avec mention de l'organisation destinataire et de l'usage éventuel de l'IA.
- Durée de conservation configurable par organisation (ex. 24 mois après clôture), puis anonymisation automatique par tâche planifiée.
- Le candidat peut exporter ses données (JSON + fichiers) et demander la suppression de son compte ; les dossiers déjà transmis sont anonymisés selon la politique de l'organisation.
- Accès superadmin aux dossiers uniquement via un accès exceptionnel motivé, journalisé et visible par l'orgadmin.
- Conformité à vérifier avec un juriste : loi nigérienne sur la protection des données personnelles et obligations des pays des organisations clientes.

### 10.3 Exigences non fonctionnelles

| Domaine | Exigence |
| --- | --- |
| Performance | Listes paginées côté serveur, fluides avec 10 000 candidatures par offre ; aucune requête N+1 (vérifiée en test) ; traitements lourds en file d'attente ; cache des pages publiques. Objectifs chiffrés à fixer selon la charge attendue (section 12) |
| Fiabilité | Transactions sur les opérations critiques ; jobs idempotents (pas de double envoi, pas de double analyse) ; reprise automatique des jobs ; sauvegarde quotidienne de la base et des fichiers, restauration testée chaque trimestre |
| Mobile et réseau | Portail candidat utilisable en 3G sur Android d'entrée de gamme, sans débordement ni fonction manquante |
| Accessibilité | Navigation clavier, libellés sur tous les champs, contrastes suffisants, erreurs annoncées aux lecteurs d'écran |
| Compatibilité | Dernières versions de Chrome, Firefox, Safari, Edge, et navigateurs mobiles courants |
| Observabilité | Journal applicatif structuré, suivi des erreurs (ex. Sentry), tableau Horizon des files d'attente |
| Maintenabilité | Conventions documentées, migrations versionnées, README d'installation, `.env.example` complet |

## 11. Phasage, recette et livrables

### 11.1 Phases

Chaque phase se termine par une démonstration et des tests qui passent avant d'ouvrir la suivante.

1. **Fondations** — projet Laravel, MySQL, Filament (panels `/admin` et `/app` multi-tenant), authentification interne et candidat, organisations, invitations, rôles et policies, trait d'isolation, journal d'audit, CI. *Livrable : une organisation est créée, invite son équipe, et les tests d'isolation passent.*
2. **Offres et formulaires** — assistant de création, critères, générateur de formulaires, prévisualisation, versionnement, cycle de vie, clôture automatique. *Livrable : un recruteur publie une offre avec son formulaire.*
3. **Vitrine et candidature** — job board, pages carrière, profil global, parcours de candidature, brouillons, upload robuste, snapshot, référence, confirmation, « Mes candidatures ». *Livrable : un candidat postule de bout en bout depuis un téléphone.*
4. **ATS** — tableau et Kanban, recherche, filtres, filtres enregistrés, recevabilité automatique, pipeline et transitions, fiche candidature, listes, actions groupées, exports. *Livrable : un recruteur trie 500 dossiers et constitue une shortlist.*
5. **Évaluation et communication** — grilles, attribution, évaluation anonyme et à l'aveugle, entretiens, délibération, décision, PV, notifications, modèles, envoi de masse, tableaux de bord, rapports. *Livrable : un recrutement complet aboutit à une décision communiquée.*
6. **Import et IA** — import Excel et CV, extraction, analyse IA, correspondance aux critères, questions d'entretien, suivi des coûts, évaluation du score. *Livrable : assistance IA activable, justifiée et désactivable.*
7. **Stabilisation** — tests de régression et de charge, audit de sécurité, optimisation, sauvegarde et restauration testées, configuration de production, documentation, recette finale.

### 11.2 Tests obligatoires

- **Isolation :** pour chaque ressource, accès depuis une autre organisation par URL, identifiant, export, fichier, action Livewire → refus.
- **Permissions :** chaque rôle sur chaque action autorisée et interdite.
- **Formulaires :** génération, validation serveur de chaque type de champ, conditions d'affichage, versionnement (une ancienne candidature reste lisible après modification).
- **Candidature :** doublon, date limite (soumission à la seconde près après clôture), fichier invalide, coupure pendant l'upload, snapshot figé.
- **ATS :** filtres combinés, transitions interdites, historique, calcul des notes pondérées.
- **Sécurité :** téléchargement direct d'un document sans autorisation, export non autorisé, modification d'un dossier sans droit.
- **IA :** aucune donnée sensible envoyée au modèle, échec de l'API sans blocage.

### 11.3 Critères de recette

LINK est accepté quand :

- [ ] Une organisation réalise un recrutement complet, de la création de l'offre à la décision communiquée, sans traitement manuel hors plateforme.
- [ ] Un candidat crée un compte, postule à deux organisations avec le même profil, et suit ses deux candidatures.
- [ ] Chaque rôle accomplit ses tâches autorisées et est refusé sur les autres.
- [ ] Aucune donnée d'une organisation n'est accessible depuis une autre (tests automatisés au vert).
- [ ] Les formulaires sont générés par offre, validés côté serveur et versionnés.
- [ ] Les documents sont privés et accessibles uniquement aux personnes autorisées.
- [ ] Les filtres, la shortlist, l'évaluation et les historiques fonctionnent sur un jeu de 10 000 candidatures de test.
- [ ] Les tests automatisés couvrent les parcours principaux et passent dans la CI.
- [ ] L'application s'installe et se déploie en suivant la documentation.
- [ ] Aucun défaut bloquant de sécurité ou d'intégrité n'est connu.

L'IA et chaque intégration externe ont leurs propres critères de recette avant activation.

### 11.4 Livrables

Code source dans un dépôt Git structuré ; migrations, seeders et données de démonstration ; tests automatisés et rapport ; `.env.example` sans secrets ; README d'installation ; guide de déploiement ; documentation des rôles et permissions ; documentation du modèle de données ; procédure de sauvegarde et restauration ; liste des limitations connues ; pour chaque intégration externe, son fournisseur, sa configuration, son coût et ses limites.

## 12. Points à confirmer avant chaque phase

Ces décisions ne doivent pas être remplacées par des hypothèses silencieuses. La colonne « Avant » indique la phase qui en dépend ; la proposition par défaut s'applique si rien n'est décidé à temps.

| Sujet | Avant | Proposition par défaut |
| --- | --- | --- |
| Inscription des organisations | Phase 1 | Sur demande, validée par le superadmin |
| Modèle commercial | Phase 1 (pour les limites) | Gratuit au lancement, avec limites techniques par organisation (offres actives, stockage) prévues dans les paramètres |
| Validation des offres | Phase 2 | Publication directe ; validation interne activable par l'organisation |
| Règle de doublon | Phase 3 | Une candidature par offre, modifiable jusqu'à la clôture si l'offre l'autorise |
| Langues | Phase 1 | Français uniquement, textes externalisés pour l'anglais |
| Hébergement | Phase 1 | VPS avec Laravel Forge ou Ploi, stockage objet S3-compatible, sauvegarde hors serveur |
| Charge attendue | Phase 4 | Hypothèse : 50 organisations, 500 offres actives, 100 000 candidatures par an |
| Barèmes et classement | Phase 5 | Notes sur 100, pondération par critère, classement par moyenne puis score IA |
| Canaux de notification | Phase 5 | Email + in-app ; WhatsApp Business ensuite |
| Fournisseur IA et budget | Phase 6 | API LLM avec accord de non-utilisation des données pour l'entraînement ; plafond de coût mensuel par organisation |
| Conservation des dossiers | Phase 7 | 24 mois après la clôture, puis anonymisation |
| Signature ou vérification de documents | Après lancement | Extension ultérieure |
