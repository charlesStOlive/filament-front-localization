<?php

namespace CharlesStOlive\FilamentFrontLocalization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un texte du front modifié depuis le B.O. : `text` porte une valeur par langue ; une langue absente garde la valeur du
 * fichier. Écrire passe par TextOverrides (journal, cache).
 *
 * @property string $namespace
 * @property string $group
 * @property string $key
 * @property array<string, string> $text
 */
class FrontTextOverride extends Model
{
    protected $table = 'front_text_overrides';

    protected $guarded = ['id'];

    protected $casts = [
        'text' => 'array',
    ];
}
