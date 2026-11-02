<?php

return [
    'navigation' => 'Front texts',
    'title' => 'Front texts',
    'description' => 'The public page texts that can be changed here. An empty field brings back the original text.',
    'columns' => [
        'group' => 'Section',
        'text' => 'Text',
    ],
    'filters' => [
        'group' => 'Section',
        'modified' => 'Changed only',
    ],
    'modified' => 'Changed',
    'actions' => [
        'edit' => 'Edit',
        'reset' => 'Restore the original text',
        'reset_confirm' => 'The original text will be restored in every language.',
        'history' => 'History',
    ],
    'form' => [
        'value' => 'Displayed text',
        'reference' => 'Original text: :value',
        'reference_missing' => 'No original text in this language.',
        'empty_keeps_reference' => 'Leave empty to keep the original text.',
        'params' => 'Keep these as they are: :params',
        'plural' => 'The forms separated by | (singular, plural) must stay separated the same way.',
        'technical_key' => 'Key: :key',
    ],
    'notifications' => [
        'saved' => 'Text saved',
        'reset' => 'Original text restored',
    ],
    'history' => [
        'empty' => 'No changes.',
        'original' => '(original text)',
        'unknown_user' => 'Unknown',
        'line' => ':date — :user — :locale',
        'reasons' => [
            'edit' => 'changed',
            'reset' => 'original text restored',
            'written' => 'written to the file',
            'pruned' => 'already in the file',
            'imported' => 'imported',
        ],
    ],
    'validation' => [
        'not_editable' => 'This text cannot be changed.',
        'params' => 'The text must keep :params.',
        'unknown_params' => 'The text contains :params, which the original text does not have.',
        'plural' => 'The text must keep :count forms separated by |.',
        'no_html' => 'The text cannot contain HTML tags.',
        'html_tags' => 'Only these tags are allowed: :tags.',
    ],
];
