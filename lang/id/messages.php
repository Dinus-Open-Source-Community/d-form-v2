<?php

declare(strict_types=1);

return [
    'event' => [
        'create' => [
            'success' => 'berhasil membuat event baru',
            'failed' => 'gagal membuat event baru'
        ],
        'edit' => [
            'success' => 'event ini berhasil diubah',
            'failed' => 'gagal mengubah event ini'
        ],
        'delete' => [
            'success' => 'event berhasil dihapus',
            'failed' => 'gagal menghapus event ini'
        ],
        'restore' => [
            'success' => 'event berhasil dipulihkan',
            'failed' => 'gagal memulihkan event ini'
        ],
        'publish' => [
            'success' => 'event berhasil dipublikasikan',
            'incomplete' => 'lengkapi data wajib event sebelum mempublikasikan'
        ],
        'draft' => [
            'success' => 'event dipindahkan ke draft'
        ],
        'force_delete' => [
            'success' => 'event berhasil dihapus permanen',
            'active' => 'hanya event terarsip yang dapat dihapus permanen'
        ],
        'archived' => [
            'read_only' => 'event sedang diarsipkan; pulihkan dulu sebelum melakukan perubahan'
        ]
    ],
    'form' => [
        'export' => [
            'too_large' => 'Arsip terlalu besar. Unduh CSV atau kurangi jumlah jawaban.',
        ],
        'create' => [
            'success' => 'berhasil membuat form baru',
            'failed' => 'gagal membuat form baru'
        ],
        'edit' => [
            'success' => 'form ini berhasil diubah',
            'failed' => 'gagal mengubah form ini'
        ],
        'delete' => [
            'success' => 'form berhasil dihapus',
            'failed' => 'gagal menghapus form ini'
        ]
    ]
];
