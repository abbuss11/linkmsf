<?php

return [
    'resource' => [
        'label' => 'membre',
        'plural_label' => 'Membres',
    ],
    'fields' => [
        'name' => 'Nom',
        'email' => 'E-mail',
        'role' => 'Rôle',
        'status' => 'Statut',
        'joined_at' => 'Membre depuis',
    ],
    'actions' => [
        'change_role' => 'Changer le rôle',
        'suspend' => 'Suspendre',
        'reactivate' => 'Réactiver',
        'remove' => 'Retirer de l’organisation',
        'remove_confirmation' => 'La personne perd l’accès à cette organisation. Son compte n’est pas supprimé.',
    ],
    'notifications' => [
        'role_changed' => 'Rôle modifié.',
        'suspended' => 'Membre suspendu.',
        'reactivated' => 'Membre réactivé.',
        'removed' => 'Membre retiré.',
    ],
    'errors' => [
        'last_admin' => 'L’organisation doit garder au moins un administrateur actif.',
    ],
];
