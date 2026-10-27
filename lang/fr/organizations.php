<?php

return [
    'resource' => [
        'label' => 'organisation',
        'plural_label' => 'Organisations',
    ],
    'fields' => [
        'name' => 'Nom',
        'slug' => 'Identifiant d’URL',
        'slug_help' => 'Minuscules, chiffres et tirets. Utilisé dans /app/… et /o/…',
        'status' => 'Statut',
        'timezone' => 'Fuseau horaire',
        'country' => 'Pays (code ISO)',
        'logo' => 'Logo (PNG, JPEG ou WebP, 1 Mo max.)',
        'primary_color' => 'Couleur principale',
        'secondary_color' => 'Couleur secondaire',
        'listed_on_job_board' => 'Apparaître dans le job board commun',
        'members_count' => 'Membres',
        'created_at' => 'Créée le',
        'admin_email' => 'E-mail du premier administrateur',
        'max_active_job_postings' => 'Offres actives maximum',
        'storage_quota_mb' => 'Stockage maximum (Mo)',
    ],
    'profile' => [
        'title' => 'Profil de l’organisation',
        'identity' => 'Identité',
        'career_page' => 'Page carrière',
        'slug_help' => 'L’identifiant d’URL ne peut être modifié que par l’équipe LINK.',
        'listed_help' => 'Si désactivé, vos offres restent visibles sur votre page carrière uniquement.',
    ],
    'admin' => [
        'first_admin' => 'Premier administrateur',
        'first_admin_help' => 'Une invitation lui sera envoyée par e-mail.',
        'limits' => 'Limites et visibilité',
    ],
    'actions' => [
        'activate' => 'Activer',
        'suspend' => 'Suspendre',
    ],
    'notifications' => [
        'activate' => 'Organisation activée.',
        'suspend' => 'Organisation suspendue. Ses membres n’y ont plus accès.',
    ],
];
