<?php

return [
    'fields' => [
        'name' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'password' => 'Mot de passe',
        'password_help' => 'Au moins 10 caractères, avec des lettres et des chiffres.',
        'password_confirmation' => 'Confirmez le mot de passe',
    ],
    'register' => [
        'title' => 'Créer un compte candidat',
        'intro' => 'Un seul compte pour postuler à toutes les organisations présentes sur LINK.',
        'submit' => 'Créer mon compte',
        'have_account' => 'Déjà inscrit ?',
    ],
    'login' => [
        'title' => 'Connexion candidat',
        'remember' => 'Rester connecté sur cet appareil',
        'submit' => 'Se connecter',
        'forgot' => 'Mot de passe oublié ?',
        'no_account' => 'Pas encore de compte ?',
        'staff_hint' => 'Vous êtes membre d’une organisation ?',
        'staff_link' => 'Connectez-vous à l’espace organisation',
    ],
    'forgot' => [
        'title' => 'Mot de passe oublié',
        'intro' => 'Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.',
        'submit' => 'Envoyer le lien',
        'sent' => 'Si un compte candidat existe pour cette adresse, un lien de réinitialisation vient d’être envoyé.',
    ],
    'reset' => [
        'title' => 'Nouveau mot de passe',
        'submit' => 'Enregistrer',
    ],
    'verify' => [
        'title' => 'Vérifiez votre adresse e-mail',
        'intro' => 'Nous avons envoyé un lien de vérification à :email. Ouvrez-le pour activer votre compte. Pensez à regarder dans les courriers indésirables.',
        'resend' => 'Renvoyer le lien',
        'sent' => 'Un nouveau lien de vérification vient d’être envoyé.',
        'done' => 'Votre adresse e-mail est vérifiée.',
    ],
    'dashboard' => [
        'title' => 'Mon espace',
        'greeting' => 'Bonjour :name',
        'applications_title' => 'Mes candidatures',
        'applications_empty' => 'Vous n’avez pas encore de candidature. Les offres seront bientôt disponibles.',
        'profile_title' => 'Mon profil',
        'profile_soon' => 'Votre profil complet (formation, expériences, documents) sera disponible prochainement.',
    ],
    'mail' => [
        'verify' => [
            'subject' => 'Vérifiez votre adresse e-mail',
            'intro' => 'Bienvenue sur LINK. Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail.',
            'action' => 'Vérifier mon adresse',
            'outro' => 'Si vous n’avez pas créé de compte, ignorez cet e-mail.',
        ],
        'reset' => [
            'subject' => 'Réinitialisation de votre mot de passe',
            'intro' => 'Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.',
            'action' => 'Choisir un nouveau mot de passe',
            'expires' => 'Ce lien expire dans :count minutes.',
            'outro' => 'Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.',
        ],
    ],
];
