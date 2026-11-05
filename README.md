# charlesstolive/filament-front-localization

Les textes du front modifiables depuis le B.O. Filament. Les fichiers de langue restent la référence : seules les
modifications vont en base, langue par langue. Un champ vidé reprend le texte d'origine. Spécification :
`docs/specifications/cdc-localisation.md` de desapp (§9 à §12).

Il ne sert pas à traduire le B.O. (fichiers de Laravel et de Filament), ni à choisir la langue d'une page
(`charlesstolive/livewire-localization`).

Deux façons de modifier ces textes : l'écran « Textes du front » (tout le catalogue dans un tableau), ou les formulaires
de l'application (`Forms\FrontText`, par exemple dans une page de réglages, à côté des réglages de la même partie). Il
apporte aussi des champs par langue pour les données des formulaires (`Forms\Localized`, `Section::localized()`).
Voir « Les textes dans un formulaire » et « Des champs par langue dans les formulaires ».

## Installation

```bash
composer require charlesstolive/filament-front-localization
php artisan vendor:publish --tag=filament-front-localization-config   # le catalogue
php artisan migrate                                                    # front_text_overrides, front_text_changes
```

```php
->plugin(FilamentFrontLocalizationPlugin::make())                    // avec l'écran « Textes du front »
->plugin(FilamentFrontLocalizationPlugin::make()->textsPage(false))  // sans : les textes sont dans des formulaires (FrontText)
```

Avec filament-permission-manager, `permissions:sync` crée la permission de l'écran (`pages.fronttexts.viewany`) ; elle
donne la lecture, la modification et le retour au texte d'origine. Sans lui, l'écran est ouvert à tout le panel. Sans
l'écran, la permission n'est plus créée : un texte dans un formulaire suit la permission de sa page.
`permissions:sync --cleanup` retire l'ancienne quand plus aucun rôle ne l'a.

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
`text` (par défaut) : aucune balise ; `html` : celles de `html_tags`. `input` (`text` ou `textarea`) choisit le champ
d'un FrontText ; sans lui, un texte d'origine long (plus de 80 caractères), sur plusieurs lignes ou en html a une zone
de texte.

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

## Les textes dans un formulaire

`Forms\FrontText` met un texte du catalogue dans n'importe quel formulaire Filament, avec l'allure d'un champ par langue
(pastilles, section `->localized()`) et le comportement de l'écran « Textes du front » :

```php
use CharlesStOlive\FilamentFrontLocalization\Filament\Concerns\SavesFrontTexts;
use CharlesStOlive\FilamentFrontLocalization\Forms\FrontText;

class DesSettingsPage extends SettingsPage
{
    use SavesFrontTexts;   // les textes s'enregistrent après les réglages, dans la même transaction

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('settings')->columnSpanFull()->tabs([
                Tab::make('Adresses')->schema([/* des réglages */]),
                Tab::make('Page programme')->schema([
                    Section::make('Remerciement')->schema([
                        FrontText::make('des/programme', 'thanks.title'),
                        FrontText::make('des/programme', 'thanks.empty'),
                    ])->localized(),
                    Section::make('Collecte')->schema(FrontText::group('des/programme', ['collecte.*']))->localized(),
                ]),
            ]),
        ]);
    }
}
```

- **Le catalogue reste la liste des clés modifiables** : `FrontText::make('groupe', 'clé')` refuse une clé qui n'y est pas ;
  libellé, aide, format et champ viennent de lui. `FrontText::group('groupe', ['motif.*'])` donne les textes d'un groupe,
  dans son ordre.
- **Un champ vide affiche le texte d'origine**, en grisé dans chaque langue (le fichier de langue, ou la config d'un groupe
  `config:`). La valeur saisie est la surcharge en base, validée comme dans l'écran (paramètres, pluriels, balises) ;
  l'erreur va sur la langue fautive.
- Près du libellé : « Modifié » quand le texte n'est plus celui d'origine, la flèche (vide toutes les langues : le texte
  d'origine revient à l'enregistrement), l'horloge (l'historique, comme dans l'écran).
- **Enregistrement** : les textes ne sont pas des données de la page (`dehydrated(false)`, état sous `front_texts`). Le
  trait `SavesFrontTexts` les retire des données (`mutateFormDataBeforeSave()`) et les écrit après la page
  (`afterSave()` → `FrontText::saveAll($this->form)`), dans sa transaction : un réglage refusé n'enregistre aucun texte.
  Seuls les textes qui ont changé sont écrits, l'historique ne note que ceux-là. Une page qui a déjà l'une de ces
  méthodes y appelle `withoutFrontTexts($data)` ou `FrontText::saveAll($this->form)` elle-même.
- `FrontText::in($form, withHidden: true)` liste les textes d'un formulaire : un test de l'application peut vérifier que
  chaque clé du catalogue a sa place quelque part quand l'écran « Textes du front » est retiré.
- Le circuit `front-texts:export/import/write/prune`, le chargeur et l'historique ne changent pas : seul l'écran change.

## Des champs par langue dans les formulaires

Pour une donnée (pas un texte des fichiers de langue) qui a une valeur par langue : un réglage, une colonne JSON, un
élément de Repeater, un attribut de spatie/laravel-translatable. `Forms\Localized` enveloppe un champ et l'édite dans
chaque langue de `locales` : `TextInput::make('donate_url')` devient `donate_url.fr`, `donate_url.en`…

```php
use CharlesStOlive\FilamentFrontLocalization\Forms\Localized;

// Par champ : des pastilles [FR] [EN] à côté du libellé.
Localized::make(TextInput::make('share_image')->label('Image de partage'))->requiredDefault(),

// Par section : les pastilles dans l'en-tête valent pour tous ses champs Localized (Repeater compris) ;
// les autres champs (courrier_url) restent affichés quelle que soit la langue.
Section::make('Adresses')
    ->schema([
        Localized::make(TextInput::make('donate_url')->url())->requiredDefault(),
        Localized::make(TextInput::make('faq_url')->url())->requiredDefault(),
        TextInput::make('courrier_url')->url()->required(),
    ])
    ->localized(),   // après un éventuel afterHeader(), qui sinon remplacerait les pastilles

// Dans un Repeater ou un Group à statePath (colonne JSON) : items.{uuid}.label.fr, seo.description.fr
Repeater::make('items')->schema([TextInput::make('sku'), Localized::make(TextInput::make('label'))]),
Group::make([Localized::make(Textarea::make('description'))])->statePath('seo'),

// Un Repeater entier par langue (une liste différente par langue) : faq.fr = [...], faq.en = [...]
Localized::make(Repeater::make('faq')->schema([TextInput::make('question')])),
```

- **Toutes les langues sont dans l'état en même temps** : on change de langue dans le navigateur (Alpine), sans requête,
  et un enregistrement écrit `['fr' => …, 'en' => …]`. Rien à faire au save : une propriété `array` de
  spatie/laravel-settings, un cast `array`, un Repeater JSON le rangent tel quel ; spatie/laravel-translatable reçoit le
  tableau par `setTranslations()`.
- **Validation** : les règles du champ valent dans chaque langue (`url()`, `maxLength()`…). `requiredDefault()` exige la
  langue de référence seule (`default_locale`), `requiredAll()` toutes, `requiredLocales(['fr', 'en'])` celles-là ; sans
  eux, le `required()` du champ vaut pour toutes. Les messages nomment la langue (« lien de don (English) »).
- **Pastilles** : la langue affichée en couleur, une langue vide en pointillés (suit la saisie), une langue en erreur en
  rouge avec « ! ». Après un enregistrement refusé, la section ou le champ passe de lui-même sur la langue de la
  première erreur. Dans une section `->localized()`, chaque champ montre la langue de sa saisie (étiquette grise).
- **Repli** : une langue vide montre en grisé (placeholder) la valeur de la langue de référence, ce que le front
  affichera. `->fallbackPlaceholder(false)` pour s'en passer.
- **Valeurs d'avant** : une valeur enregistrée avant le passage par langue (une chaîne, la liste d'un Repeater) devient
  celle de la langue de référence au lieu d'être perdue. Un attribut spatie/laravel-translatable, qui arrive en chaîne
  (la langue en cours), est relu dans toutes ses langues par `getTranslation()` ; seulement au niveau du modèle, jamais
  dans un élément de Repeater JSON ou un Group à statePath.
- `->locales(['fr', 'en'])` limite un champ à certaines langues. `filament-front-localization.default_locale` choisit la
  langue de référence (par défaut celle du front, `livewire-localization.default`).
- La feuille de style des pastilles est un asset Filament : `php artisan filament:assets` (lancé par `filament:upgrade`
  au `composer update`).

Côté front, `LocaleRegistry::pick()` de livewire-localization lit ces tableaux : la langue en cours si elle est remplie,
sinon la langue par défaut, sinon la première remplie.

```php
app(LocaleRegistry::class)->pick($settings->donate_url);   // ['fr' => '…', 'en' => null] → la valeur française en /en/
```

## Tests

Ils vivent dans l'application hôte : desapp, `tests/Feature/Localization/FrontTextsTest.php`, `FrontTextsWriteTest.php`,
`FrontTextsTransferTest.php`, `LocalizedFieldsTest.php` (champs par langue, avec la fixture `LocalizedFieldsForm`) et
`DesSettingsTextsTest.php` (les textes dans les onglets de Réglages DES).
