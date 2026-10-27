<?php

return [
    'resource' => [
        'label' => 'demande d’espace',
        'plural_label' => 'Demandes d’espace',
    ],
    'fields' => [
        'organization_name' => 'Nom de l’organisation',
        'contact_name' => 'Votre nom',
        'email' => 'E-mail professionnel',
        'phone' => 'Téléphone',
        'phone_help' => 'Format international, par exemple +227 90 00 00 00.',
        'country' => 'Pays',
        'message' => 'Votre besoin (facultatif)',
        'status' => 'Statut',
        'created_at' => 'Reçue le',
        'rejection_reason' => 'Motif du refus',
    ],
    'actions' => [
        'approve' => 'Valider',
        'approve_description' => 'L’organisation sera créée et le demandeur recevra une invitation d’administrateur.',
        'reject' => 'Refuser',
    ],
    'notifications' => [
        'approved' => 'Organisation créée et invitation envoyée.',
        'rejected' => 'Demande refusée. Le demandeur a été informé.',
    ],
    'errors' => [
        'already_pending' => 'Une demande est déjà en cours pour cette adresse. Nous vous répondrons rapidement.',
        'already_reviewed' => 'Cette demande a déjà été traitée.',
        'reason_required' => 'Le motif du refus est obligatoire.',
    ],
    'notify' => [
        'subject' => 'Nouvelle demande d’espace organisation',
        'body' => ':organization (contact : :contact) demande un espace sur LINK.',
        'action' => 'Voir les demandes',
    ],
    'rejected_mail' => [
        'subject' => 'Votre demande d’espace sur LINK',
        'intro' => 'Nous avons étudié la demande d’espace pour :organization et ne pouvons pas y donner suite.',
        'reason' => 'Motif : :reason',
        'outro' => 'Vous pouvez répondre à cet e-mail pour toute question.',
    ],
    'page' => [
        'title' => 'Demander un espace organisation',
        'intro' => 'Publiez vos offres et appels à candidatures, puis traitez les dossiers dans LINK. L’équipe LINK valide chaque demande, généralement sous 48 heures.',
        'submit' => 'Envoyer la demande',
        'thanks_title' => 'Demande envoyée',
        'thanks' => 'Merci. Nous étudions votre demande et vous écrivons par e-mail dès qu’elle est validée.',
    ],
];
