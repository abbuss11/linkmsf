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
        'job_postings_requires_approval' => 'Les offres doivent être validées par un administrateur',
        'job_postings_recruiters_see_all' => 'Les recruteurs voient toutes les offres',
        'storage_quota_mb' => 'Stockage maximum (Mo)',
    ],
    'profile' => [
        'title' => 'Profil de l’organisation',
        'identity' => 'Identité',
        'career_page' => 'Page carrière',
        'slug_help' => 'L’identifiant d’URL ne peut être modifié que par l’équipe LINK.',
        'listed_help' => 'Si désactivé, vos offres restent visibles sur votre page carrière uniquement.',
        'job_postings' => 'Offres',
        'requires_approval_help' => 'Les recruteurs soumettent leurs offres ; un administrateur les publie.',
        'recruiters_see_all_help' => 'Si désactivé, chaque recruteur ne voit que les offres auxquelles il est affecté.',
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
