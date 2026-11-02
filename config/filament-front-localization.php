<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Les langues éditées
    |--------------------------------------------------------------------------
    |
    | Un onglet par langue dans l'écran. null : celles du front
    | (livewire-localization.locales), sinon la langue de l'application.
    | Sinon : ['fr' => 'Français', 'en' => 'English'].
    |
    */
    'locales' => null,

    /*
    |--------------------------------------------------------------------------
    | Le catalogue des textes modifiables
    |--------------------------------------------------------------------------
    |
    | Un groupe par partie de l'application : son identifiant est le groupe
    | Laravel (le fichier, `des/programme`), précédé du namespace pour un
    | plugin (`filament-map::front`). Seules les clés listées sont
    | modifiables ; le fichier de langue reste la référence.
    |
    | Un libellé est une clé de traduction ou un tableau par langue
    | (['fr' => '…', 'en' => '…']). Une clé déclare son format : `text`
    | (par défaut, aucune balise) ou `html` (balises de `html_tags`). Les
    | paramètres (:name) et les pluriels (|) se déduisent du texte de
    | référence.
    |
    | 'source' => 'config:chemin' : les valeurs de référence viennent de la
    | config (un texte par langue), pas des fichiers de langue ; le code qui
    | lit ces textes demande la surcharge (FrontTexts::override()).
    |
    | Un plugin Filament déclare ses groupes par une méthode statique
    | frontTexts() qui rend la même structure, sans dépendre de ce paquet.
    |
    |     'des/programme' => [
    |         'label' => ['fr' => 'DES — page programme', 'en' => 'DES — programme page'],
    |         'keys' => [
    |             'thanks.title' => ['label' => ['fr' => 'Remerciement', 'en' => 'Thanks'], 'help' => …],
    |         ],
    |     ],
    |
    */
    'texts' => [],

    // Les balises permises dans un texte au format html.
    'html_tags' => '<br><strong><em><a>',

    // L'écran dans le panel.
    'navigation' => [
        'group' => null,
        'sort' => null,
    ],
];
