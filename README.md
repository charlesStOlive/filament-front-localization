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

## Reporter les textes dans le code

Les textes modifiés dans le B.O. peuvent devenir les textes d'origine : ils remplacent la valeur dans le fichier de langue
(ou dans la config, pour un groupe `config:`), à sa place exacte (commentaires, mise en forme, espaces insécables et
appels comme `env()` restent tels quels), puis sortent de la base.

Les textes sont corrigés en production, les fichiers vivent dans git. Le circuit :

```bash
# 1. En production : les textes modifiés, en JSON
php artisan front-texts:export                      # storage/app/front-texts/front-texts-AAAA-MM-JJ-HHMM.json
php artisan front-texts:export - > textes.json      # ou sur la sortie standard (ssh …)

# 2. En dev : les importer (chacun est revalidé contre les fichiers d'ici), puis les écrire
php artisan front-texts:import textes.json [--replace] [--dry-run]
php artisan front-texts:write --dry-run
php artisan front-texts:write                       # puis relire git diff lang config, committer, déployer

# 3. En production, après le déploiement : retirer de la base ce que les fichiers portent désormais
php artisan front-texts:prune [--dry-run]
```

Chaque commande accepte `--group=` (jokers : `--group='des/*'`, `--group='mail-access/*'`) et `--only=lang|config`.
`front-texts:write --keep` écrit sans vider la base. `front-texts:import --replace` fait reprendre exactement le fichier
aux groupes importés (les textes de dev absents du fichier sont retirés).

**Interdites en production** : `front-texts:write` (les fichiers écrits seraient écrasés au prochain déploiement, et les
textes retirés de la base perdus) et `front-texts:import` (il écraserait les textes des rédacteurs). `prune` ne retire
jamais un texte que le fichier n'a pas : un texte modifié depuis l'export reste.

Non écrits (la base les garde, la commande le dit) : un texte de plugin (son fichier est dans le plugin), une clé absente
du fichier de cette langue, une valeur qui n'y est pas une chaîne simple. L'historique de l'écran note la raison de
chaque changement : modifié, texte d'origine rétabli, importé, écrit dans le fichier, déjà dans le fichier.

## Tests

Ils vivent dans l'application hôte : desapp, `tests/Feature/Localization/FrontTextsTest.php`, `FrontTextsWriteTest.php` et `FrontTextsTransferTest.php`.
