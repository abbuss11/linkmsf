# Décisions techniques

| Date | Décision | Raison |
| --- | --- | --- |
| 2026-10-27 | Filament 5 + Livewire 4 (au lieu de Livewire 3) | Filament 5, dernière version stable, exige Livewire 4. Validé avant la phase 1. |
| 2026-10-27 | Pas de spatie/laravel-permission | Le rôle est déjà dans `organization_memberships` (§9.1). Les rôles sont fixes : une correspondance rôle → permissions dans un Enum évite de stocker le rôle deux fois et l'état global « team id » à réinitialiser dans chaque job. Validé avant la phase 1. |
| 2026-10-27 | `users.type` exclusif (interne / candidat) | Conforme au §9.1. Conséquence : un membre d'organisation qui veut postuler doit utiliser une autre adresse e-mail. |
| 2026-10-27 | PHP 8.3 ; Pest 4 et activitylog 4 (au lieu de Pest 5 et activitylog 5) | Les versions 5 exigent PHP 8.4 et la stack fige PHP 8.3+. Plateforme Composer fixée à 8.3. Passage à PHP 8.4 possible plus tard sans changement de code. |
| 2026-10-27 | Pas de laravel-lang/common | Traductions françaises du framework écrites dans `lang/fr` : une dépendance de moins. |
| 2026-10-27 | Pages publiques en Blade sans Livewire, polices système | Légèreté en 3G : ~3 à 8 Ko de HTML + 4 Ko de CSS compressé, fonctionnement sans JavaScript. |
| 2026-10-27 | Inscription des organisations sur demande, validée par le superadmin | Proposition par défaut du §12. |
| 2026-10-27 | Limites par organisation dans `organizations.settings.limits` | Proposition par défaut du §12 (gratuit, limites techniques). Appliquées à partir des phases 2 et 3. |
| 2026-10-27 | Limites de débit larges par IP, strictes par compte | Au Niger, les opérateurs mobiles placent de nombreux utilisateurs derrière la même IP publique (CGNAT). |
| 2026-10-27 | Turnstile qui échoue « fermé » | Si Cloudflare est injoignable, la soumission est refusée avec un message ; le honeypot reste actif quand Turnstile est désactivé. |
| 2026-10-27 | Lien d'invitation signé sans expiration d'URL | L'expiration de l'invitation (7 jours) est vérifiée par l'application, qui affiche un message clair au lieu d'un 403 brut. Le jeton (64 caractères) est stocké haché ; renvoyer une invitation invalide l'ancien lien. |
| 2026-10-27 | Logo d'organisation sur le disque `public` | Le logo est public par nature (page carrière). Nom en UUID, PNG/JPEG/WebP uniquement (pas de SVG), 1 Mo max., type MIME réel vérifié. Les documents des candidats n'utiliseront jamais ce disque. |
| 2026-10-27 | Notifications contenant un jeton chiffrées dans la file (`ShouldBeEncrypted`) | Le jeton ne doit pas apparaître en clair dans Redis ni dans `failed_jobs`. |
| 2026-10-30 | Slug d'offre unique sur toute la plateforme (`titre-organisation`, suffixe si besoin) | Contradiction §4.1 (URL `/offres/{slug}` commune) / §9.1 (unicité par organisation) : l'unicité globale satisfait les deux. Le slug suit le titre jusqu'à la première publication, puis ne change plus. |
| 2026-10-30 | Recruteurs : toutes les offres visibles par défaut | §2.1 « offres assignées (ou toutes, selon paramètre) » : réglage d'organisation, désactivable par l'orgadmin ; le créateur d'une offre y est affecté. |
| 2026-10-30 | Validation interne des offres désactivée par défaut | Proposition par défaut du §12 (phase 2). |
| 2026-10-30 | Fichiers : PDF, JPG, PNG, DOCX ; 5 Mo et 3 fichiers par défaut ; plafonds 10 Mo et 10 fichiers | Connexions mobiles lentes. Type MIME vérifié sur le contenu réel (`mimetypes`), pas sur le nom. Pas de SVG. |
| 2026-10-30 | symfony/html-sanitizer pour la description riche | La description sera publiée en phase 3 : liste blanche de balises à l'enregistrement (pas de script, style, image, iframe ; liens http/https/mailto en `nofollow`). |
| 2026-10-30 | Pièces à fournir = champs fichier du formulaire marqués `document` | Le formulaire reste l'unique description de ce qui est demandé ; les pièces sont gérées à l'étape 3, les questions à l'étape 4. |
| 2026-10-30 | Recevabilité : règles définies et vérifiées en phase 2, évaluées en phase 4 | Les candidatures n'existent pas encore ; la compatibilité règle / champ est contrôlée dès la saisie et bloquante à la publication. |
| 2026-10-30 | Une nouvelle version de formulaire seulement si le schéma change (empreinte SHA-256) | Évite les versions vides ; une clé publiée ne peut plus changer de type. |
| 2026-10-30 | `queryAcrossOrganizations()` retire aussi le scope de tenant Filament (`app_tenancy`) | Sans cela, une requête explicitement inter-organisations restait filtrée par le tenant Filament courant. |
| 2026-10-30 | Formulaire candidat en Blade + Alpine dans un composant Livewire (pas de Filament côté candidat) ; l'aperçu du back-office utilise les mêmes composants, dans un iframe | Validé avant la phase 3. Page de l'assistant ≈ 30 Ko transférés ; ce que voit le recruteur est ce que verra le candidat. `FilamentFormRenderer` supprimé. |
| 2026-10-30 | Envoi des fichiers par morceaux de 512 Ko, avec reprise | Validé avant la phase 3. Le serveur garde le nombre d'octets reçus ; un décalage répond 409 avec ce nombre, le navigateur reprend de là. Empreinte SHA-256 (si le navigateur la calcule) : un fichier déjà reçu n'est pas renvoyé. Type MIME vérifié sur le fichier assemblé ; un envoi abandonné depuis 30 min ne bloque pas un nouveau fichier. |
| 2026-10-30 | Copie figée du profil (`profile_snapshot`) et réponses typées à la soumission | §4.3 : l'organisation ne lit jamais le profil en direct. Les réponses sont stockées par type (texte, nombre, date, liste) pour les filtres de la phase 4. |
| 2026-10-30 | Référence `LNK-AAAA-XXX-000123` : compteur par organisation et par an, verrouillé | Lisible au téléphone ; pas de trou ni de doublon en cas de soumissions simultanées. `XXX` = trois premières lettres du slug. |
| 2026-10-30 | Doublons : une candidature par personne et par offre (contrainte unique) ; pas de nouvelle candidature après un retrait | Proposition par défaut du §12. Si l'offre l'autorise, la candidature envoyée reste modifiable jusqu'à la date limite, avec une révision conservée à chaque renvoi. |
| 2026-10-30 | Genre facultatif dans le profil, jamais transmis à l'IA | Validé avant la phase 3 (§10.2). |
| 2026-10-30 | Rappel e-mail 48 h avant la clôture pour les brouillons, une seule fois | Validé avant la phase 3. Commande `applications:remind-drafts`, toutes les heures. |
| 2026-10-30 | Cache navigateur de 60 s (`private`) sur les pages publiques pour les visiteurs anonymes | `private` et non `public` : Laravel ajoute un cookie de session à chaque réponse, qu'un proxy partagé ne doit pas stocker. |
| 2026-10-30 | Image de partage 1200×630 générée avec GD et DejaVu Sans (police libre incluse) | Pas de service externe ni de dépendance Composer ; régénérée à la publication et quand le titre, le lieu ou la date limite changent. |
| 2026-10-30 | Recherche plein texte MySQL (`FULLTEXT` sur titre + texte de recherche), `LIKE` sous 3 caractères | Suffisant au volume prévu, sans moteur de recherche externe. Testée dans `tests/Integration` (les index FULLTEXT ne voient que les lignes validées : ces tests vident les tables au lieu d'utiliser une transaction). |
| 2026-10-30 | Les écouteurs `JobProcessing` / `JobProcessed` ne vident plus le contexte d'organisation pour la file `sync` | Un job exécuté dans la requête (tests, environnement sans worker) effaçait le contexte de la requête en cours. |
