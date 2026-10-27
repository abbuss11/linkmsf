<?php

return [
    'resource' => [
        'label' => 'invitation',
        'plural_label' => 'Invitations',
    ],
    'fields' => [
        'email' => 'E-mail',
        'role' => 'Rôle',
        'status' => 'Statut',
        'invited_by' => 'Invité par',
        'expires_at' => 'Expire le',
        'created_at' => 'Envoyée le',
    ],
    'actions' => [
        'invite' => 'Inviter un membre',
        'resend' => 'Renvoyer',
        'revoke' => 'Révoquer',
    ],
    'notifications' => [
        'sent' => 'Invitation envoyée.',
        'resent' => 'Invitation renvoyée. L’ancien lien ne fonctionne plus.',
        'revoked' => 'Invitation révoquée.',
    ],
    'errors' => [
        'invalid_email' => 'Adresse e-mail invalide.',
        'organization_inactive' => 'Cette organisation n’est pas active.',
        'email_is_candidate' => 'Cette adresse est celle d’un compte candidat. Un compte candidat ne peut pas rejoindre une organisation : utilisez une autre adresse.',
        'already_member' => 'Cette personne est déjà membre de l’organisation.',
        'already_invited' => 'Une invitation est déjà en attente pour cette adresse. Vous pouvez la renvoyer.',
        'invalid_or_expired' => 'Cette invitation n’est plus valide. Demandez une nouvelle invitation.',
        'wrong_account' => 'Vous êtes connecté avec un autre compte que celui invité.',
        'login_required' => 'Un compte existe déjà pour cette adresse : connectez-vous pour accepter l’invitation.',
    ],
    'mail' => [
        'subject' => 'Invitation à rejoindre :organization sur LINK',
        'greeting' => 'Bonjour,',
        'intro' => 'Vous êtes invité à rejoindre :organization sur LINK en tant que :role.',
        'intro_with_inviter' => ':inviter vous invite à rejoindre :organization sur LINK en tant que :role.',
        'action' => 'Accepter l’invitation',
        'expires' => 'Ce lien est valable jusqu’au :date.',
        'ignore' => 'Si vous ne vous attendiez pas à cette invitation, ignorez cet e-mail.',
    ],
    'accepted_mail' => [
        'subject' => 'Nouveau membre dans :organization',
        'body' => ':name a rejoint :organization en tant que :role.',
    ],
    'page' => [
        'title' => 'Invitation',
        'invalid' => 'Ce lien d’invitation n’est plus valide (expiré, révoqué ou déjà utilisé). Demandez une nouvelle invitation à l’administrateur de l’organisation.',
        'intro' => 'Vous êtes invité à rejoindre :organization en tant que :role.',
        'wrong_account' => 'Vous êtes connecté avec un autre compte. Déconnectez-vous puis rouvrez le lien pour continuer avec l’adresse :email.',
        'login_required' => 'Un compte existe déjà pour :email. Connectez-vous, vous serez ramené ici.',
        'login' => 'Se connecter',
        'accept' => 'Accepter l’invitation',
        'create_account' => 'Créez votre compte pour :email.',
        'accept_and_create' => 'Créer mon compte et rejoindre',
    ],
];
