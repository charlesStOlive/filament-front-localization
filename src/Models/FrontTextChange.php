<?php

namespace CharlesStOlive\FilamentFrontLocalization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du journal : une langue d'un texte, avant et après (null : le texte du fichier), et qui l'a changée. */
class FrontTextChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'front_text_changes';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo((string) config('auth.providers.users.model'), 'user_id');
    }
}
