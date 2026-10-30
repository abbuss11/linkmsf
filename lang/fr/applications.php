<?php

return [
    'consent' => 'J’accepte que mes données soient transmises à :organization pour l’étude de ma candidature, et je certifie l’exactitude des informations fournies.',
    'withdrawn' => 'Votre candidature a été retirée.',

    'errors' => [
        'candidate_only' => 'Seul un compte candidat vérifié peut postuler.',
        'closed' => 'Cette offre n’accepte plus de candidatures.',
        'withdrawn' => 'Vous avez retiré votre candidature à cette offre : il n’est pas possible de postuler à nouveau.',
        'already_applied' => 'Vous avez déjà postulé à cette offre.',
        'unknown_field' => 'Ce document n’est pas demandé pour cette offre.',
        'file_type' => 'Format non accepté. Formats autorisés : :allowed.',
        'file_size' => 'Fichier trop volumineux (maximum :max Mo).',
        'too_many_files' => 'Nombre maximal de fichiers atteint (:max).',
        'chunk' => 'Le fichier n’a pas été reçu correctement. Veuillez réessayer.',
        'checksum' => 'Le fichier reçu est différent du fichier envoyé. Veuillez réessayer.',
        'file_content' => 'Le contenu du fichier ne correspond pas à un format autorisé (:allowed).',
        'consent' => 'Vous devez accepter la transmission de vos données pour envoyer votre candidature.',
        'document_required' => 'Le document « :document » est obligatoire.',
        'not_modifiable' => 'Cette candidature ne peut plus être modifiée (date limite passée ou modification non autorisée par l’organisation).',
        'too_many_attempts' => 'Trop de tentatives. Réessayez dans quelques minutes.',
    ],

    'wizard' => [
        'title' => 'Postuler : :title',
        'progress' => 'Étapes de la candidature',
        'steps' => [
            'profile' => 'Profil',
            'questions' => 'Questions',
            'documents' => 'Documents',
            'review' => 'Vérification',
        ],
        'editing' => 'Vous modifiez votre candidature :reference. Pensez à la renvoyer à la fin.',
        'profile_intro' => 'Ces informations viennent de votre profil. Vérifiez-les avant de continuer.',
        'update_profile' => 'Mettre aussi à jour mon profil avec ces informations',
        'documents_intro' => 'Envoyez vos documents. Si la connexion coupe, l’envoi reprend là où il s’est arrêté.',
        'file_constraints' => 'Formats : :formats · :size Mo maximum · :count fichier(s) au plus',
        'remove' => 'Retirer',
        'remove_confirm' => 'Retirer ce document ?',
        'reuse' => 'Ou réutilisez un document de votre profil :',
        'review_title' => 'Vérifiez votre candidature',
        'fix_errors' => 'Certaines informations sont manquantes ou incorrectes. Corrigez-les à l’étape indiquée.',
        'previous' => 'Précédent',
        'next' => 'Enregistrer et continuer',
        'saving' => 'Enregistrement…',
        'submit' => 'Envoyer ma candidature',
        'resubmit' => 'Renvoyer ma candidature modifiée',
        'sending' => 'Envoi en cours…',
        'autosave' => 'Votre brouillon est enregistré à chaque étape : vous pouvez reprendre plus tard, sur n’importe quel appareil.',
    ],

    'upload' => [
        'choose' => 'Choisir un fichier',
        'network' => 'Connexion interrompue. Nouvel essai…',
        'failed' => 'L’envoi a échoué. Vérifiez votre connexion puis réessayez : il reprendra là où il s’est arrêté.',
        'retry' => 'Réessayer',
    ],

    'show' => [
        'title' => 'Ma candidature',
        'reference' => 'Référence',
        'status' => 'Statut',
        'submitted_at' => 'Envoyée le',
        'draft_notice' => 'Cette candidature n’est pas encore envoyée.',
        'continue' => 'Continuer ma candidature',
        'edit' => 'Modifier ma candidature',
        'withdraw' => 'Retirer ma candidature',
        'withdraw_confirm' => 'Retirer définitivement votre candidature ? Vous ne pourrez pas postuler à nouveau à cette offre.',
        'answers' => 'Mes réponses',
        'documents' => 'Mes documents',
        'download' => 'Télécharger',
        'view_offer' => 'Voir l’offre',
        'deadline' => 'Date limite : :date',
    ],

    'confirmation' => [
        'title' => 'Candidature envoyée',
        'heading' => 'Votre candidature est bien envoyée',
        'body' => 'Votre candidature au poste « :title » chez :organization a été reçue le :date.',
        'reference' => 'Conservez votre référence : :reference',
        'email' => 'Un e-mail de confirmation vous a été envoyé.',
        'follow' => 'Suivre ma candidature',
        'more' => 'Voir d’autres offres',
    ],

    'mail' => [
        'submitted' => [
            'subject' => 'Candidature reçue (:reference)',
            'body' => 'Votre candidature au poste « :title » chez :organization a bien été reçue.',
        ],
        'updated' => [
            'subject' => 'Candidature modifiée (:reference)',
            'body' => 'Votre candidature modifiée au poste « :title » chez :organization a bien été reçue.',
        ],
        'reference' => 'Votre référence : :reference',
        'action' => 'Suivre ma candidature',
        'reminder' => [
            'subject' => 'Candidature non envoyée : :title',
            'body' => 'Votre candidature au poste « :title » chez :organization n’est pas encore envoyée. La date limite est le :deadline.',
            'action' => 'Terminer ma candidature',
        ],
    ],
];
