# charlesstolive/filament-front-localization

Les textes du front modifiables depuis le B.O. Filament. Les fichiers de langue restent la référence : seules les
modifications vont en base, langue par langue. Un champ vidé reprend le texte d'origine. Spécification :
`docs/specifications/cdc-localisation.md` de desapp (§9 à §12).

Il ne sert pas à traduire le B.O. (fichiers de Laravel et de Filament), ni à choisir la langue d'une page
(`charlesstolive/livewire-localization`).

## Installation

```bash
composer require charlesstolive/filament-front-localization
php artisan vendor:publish --tag=filament-front-localization-config   # le catalogue
php artisan migrate                                                    # front_text_overrides, front_text_changes
```

```php
->plugin(FilamentFrontLocalizationPlugin::make())   // l'écran « Textes du front »
```

Avec filament-permission-manager, `permissions:sync` crée la permission de l'écran (`pages.fronttexts.viewany`) ; elle
donne la lecture, la modification et le retour au texte d'origine. Sans lui, l'écran est ouvert à tout le panel.

## Le catalogue

Seules les clés déclarées sont modifiables, dans `config/filament-front-localization.php` :

```php
'texts' => [
    // Un groupe Laravel : lang/{fr,en}/des/programme.php, clés des/programme.thanks.title…
    'des/programme' => [
        'label' => ['fr' => 'DES — page programme', 'en' => 'DES — programme page'],
        'keys' => [
            'thanks.title' => ['label' => ['fr' => 'Remerciement', 'en' => 'Thanks']],
            'contact.title' => ['label' => 'des.front_texts.contact', 'format' => 'text'],
        ],
    ],
    // Un plugin : son namespace devant le groupe.
    'filament-map::front' => ['keys' => ['search.placeholder' => []]],
    // Des textes en config (une valeur par langue) : la config est la référence.
    'mail-access/sponsors-des-access' => [
        'source' => 'config:mail-access.services.sponsors-des-access',
        'keys' => ['messages.page_title' => []],
    ],
],
```

Un plugin Filament déclare ses propres groupes, sans dépendre de ce paquet, par une méthode statique :

```php
public static function frontTexts(): array
{
    return ['filament-map::front' => ['keys' => ['search.placeholder' => []]]];
}
```

Les paramètres (`:name`) et les pluriels (`|`) se déduisent du texte d'origine : un texte modifié doit les garder. Format
`text` (par défaut) : aucune balise ; `html` : celles de `html_tags`.

## Comment les textes arrivent

Le chargeur de traductions de Laravel est décoré, pas remplacé : les fichiers d'abord (chemins, namespaces, surcharges
`lang/vendor`), puis, pour un groupe du catalogue seulement, les textes modifiés dans la langue demandée. `__()`,
`trans_choice()` et les vues n'ont rien à changer. Une langue sans texte modifié garde son fichier, sans repli sur une
autre langue. Un groupe absent du catalogue ne coûte aucune requête ; un groupe du catalogue, une requête mise en cache
et vidée à chaque écriture.

Un texte qui ne vient pas des fichiers de langue (une config par langue) demande sa surcharge :

```php
$text = app(FrontTexts::class)->override('mail-access/sponsors-des-access', 'messages.page_title') ?? $configValue;
```

Un worker de file d'attente garde les traductions qu'il a déjà chargées : `queue:restart` après une modification si des
courriels en file utilisent ces textes par `__()`.

## Tests

Ils vivent dans l'application hôte : desapp, `tests/Feature/Localization/FrontTextsTest.php`.
