<?php

return [
    'navigation' => 'Textes du front',
    'title' => 'Textes du front',
    'description' => 'Les textes des pages publiques qui peuvent être modifiés ici. Un champ vide reprend le texte d’origine.',
    'columns' => [
        'group' => 'Partie',
        'text' => 'Texte',
    ],
    'filters' => [
        'group' => 'Partie',
        'modified' => 'Modifiés seulement',
    ],
    'modified' => 'Modifié',
    'actions' => [
        'edit' => 'Modifier',
        'reset' => 'Revenir au texte d’origine',
        'reset_confirm' => 'Le texte d’origine reviendra dans toutes les langues.',
        'history' => 'Historique',
    ],
    'form' => [
        'value' => 'Texte affiché',
        'reference' => 'Texte d’origine : :value',
        'reference_missing' => 'Pas de texte d’origine dans cette langue.',
        'empty_keeps_reference' => 'Laisser vide pour garder le texte d’origine.',
        'params' => 'À garder tels quels : :params',
        'plural' => 'Les formes séparées par | (singulier, pluriel) doivent rester séparées de la même façon.',
        'technical_key' => 'Clé : :key',
    ],
    'notifications' => [
        'saved' => 'Texte enregistré',
        'reset' => 'Texte d’origine rétabli',
    ],
    'history' => [
        'empty' => 'Aucune modification.',
        'original' => '(texte d’origine)',
        'unknown_user' => 'Inconnu',
        'line' => ':date — :user — :locale',
        'reasons' => [
            'edit' => 'modifié',
            'reset' => 'texte d’origine rétabli',
            'written' => 'écrit dans le fichier',
            'pruned' => 'déjà dans le fichier',
            'imported' => 'importé',
        ],
    ],
    'validation' => [
        'not_editable' => 'Ce texte n’est pas modifiable.',
        'params' => 'Le texte doit garder :params.',
        'unknown_params' => 'Le texte contient :params, qui n’existe pas dans le texte d’origine.',
        'plural' => 'Le texte doit garder :count formes séparées par |.',
        'no_html' => 'Le texte ne peut pas contenir de balises HTML.',
        'html_tags' => 'Seules ces balises sont permises : :tags.',
    ],
    'localized' => [
        'switcher' => 'Langue du contenu',
        'reset_hint' => 'Vider toutes les langues : le texte d’origine reviendra à l’enregistrement.',
    ],
];
