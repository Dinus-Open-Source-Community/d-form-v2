<?php

declare(strict_types=1);

return [
    'event' => [
        'create' => [
            'success' => 'new event created successfully',
            'failed' => 'failed to create new event'
        ],
        'edit' => [
            'success' => 'this event has been edited',
            'failed' => 'failed to edit this event'
        ],
        'delete' => [
            'success' => 'event deleted successfully',
            'failed' => 'failed to delete this event'
        ],
        'restore' => [
            'success' => 'event restored successfully',
            'failed' => 'failed to restore this event'
        ],
        'publish' => [
            'success' => 'event published successfully',
            'incomplete' => 'complete the required event details before publishing'
        ],
        'draft' => [
            'success' => 'event moved to draft'
        ],
        'force_delete' => [
            'success' => 'event permanently deleted',
            'active' => 'archived events only can be permanently deleted'
        ],
        'archived' => [
            'read_only' => 'event is archived; restore it before making changes'
        ]
    ],
    'form' => [
        'export' => [
            'too_large' => 'The archive is too large. Download the CSV or reduce the submissions.',
        ],
        'create' => [
            'success' => 'new form created successfully',
            'failed' => 'failed to create new form'
        ],
        'edit' => [
            'success' => 'this form has been edited',
            'failed' => 'failed to edit this form'
        ],
        'delete' => [
            'success' => 'form deleted successfully',
            'failed' => 'failed to delete this form'
        ]
    ]
];
