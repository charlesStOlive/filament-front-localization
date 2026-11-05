<?php

namespace CharlesStOlive\FilamentFrontLocalization\Filament\Concerns;

use CharlesStOlive\FilamentFrontLocalization\Forms\FrontText;

/**
 * Pour une page dont le formulaire contient des FrontText (SettingsPage du plugin spatie-laravel-settings, EditRecord) :
 * les textes ne partent pas avec les données de la page, ils sont enregistrés après elles, dans la même transaction.
 *
 * Une page qui a déjà son mutateFormDataBeforeSave() ou son afterSave() les garde (ceux de la classe passent avant ceux
 * du trait) : elle y appelle alors withoutFrontTexts() et FrontText::saveAll($this->form) elle-même.
 */
trait SavesFrontTexts
{
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withoutFrontTexts($data);
    }

    protected function afterSave(): void
    {
        FrontText::saveAll($this->form);
    }

    /** @param  array<string, mixed>  $data */
    protected function withoutFrontTexts(array $data): array
    {
        unset($data[FrontText::STATE_KEY]);

        return $data;
    }
}
