<?php

return [
    'user_type' => [
        'internal' => 'Organisation / plateforme',
        'candidate' => 'Candidat',
    ],
    'organization_role' => [
        'orgadmin' => 'Administrateur',
        'recruiter' => 'Recruteur',
        'jury' => 'Membre du jury',
    ],
    'organization_status' => [
        'pending' => 'En attente',
        'active' => 'Active',
        'suspended' => 'Suspendue',
    ],
    'organization_request_status' => [
        'pending' => 'À traiter',
        'approved' => 'Validée',
        'rejected' => 'Refusée',
    ],
    'membership_status' => [
        'active' => 'Actif',
        'suspended' => 'Suspendu',
    ],
    'invitation_status' => [
        'pending' => 'En attente',
        'accepted' => 'Acceptée',
        'revoked' => 'Révoquée',
        'expired' => 'Expirée',
    ],
    'audit_event' => [
        'auth_login' => 'Connexion',
        'auth_login_failed' => 'Échec de connexion',
        'auth_logout' => 'Déconnexion',
        'auth_password_reset' => 'Mot de passe réinitialisé',
        'auth_candidate_registered' => 'Inscription candidat',
        'organization_request_submitted' => 'Demande d’espace reçue',
        'organization_request_approved' => 'Demande d’espace validée',
        'organization_request_rejected' => 'Demande d’espace refusée',
        'organization_created' => 'Organisation créée',
        'organization_updated' => 'Organisation modifiée',
        'organization_suspended' => 'Organisation suspendue',
        'organization_activated' => 'Organisation activée',
        'invitation_sent' => 'Invitation envoyée',
        'invitation_resent' => 'Invitation renvoyée',
        'invitation_revoked' => 'Invitation révoquée',
        'invitation_accepted' => 'Invitation acceptée',
        'member_role_changed' => 'Rôle modifié',
        'member_suspended' => 'Membre suspendu',
        'member_reactivated' => 'Membre réactivé',
        'member_removed' => 'Membre retiré',
    ],
];
