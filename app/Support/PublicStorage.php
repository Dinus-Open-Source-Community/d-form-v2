<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** URL untuk file di disk "public" (avatar, banner event, unggahan, dll.). */
final class PublicStorage
{
    public static function url(?string $pathOnPublicDisk, ?Request $request = null): ?string
    {
        if ($pathOnPublicDisk === null || $pathOnPublicDisk === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $pathOnPublicDisk), '/');
        $suffix = '/storage/'.$path;

        $request ??= request();

        if ($request !== null && $request->getHost() !== '') {
            return rtrim($request->getSchemeAndHttpHost(), '/').$suffix;
        }

        $base = rtrim((string) config('filesystems.disks.public.url'), '/');

        return $base !== '' ? $base.$suffix : $suffix;
    }

    /**
     * URL yang hanya dipublikasikan bila file lokalnya benar-benar ada.
     *
     * Dipakai resource yang menampilkan aset dari data tersimpan: path basi pada baris lama
     * (mis. hasil seeder atau upload yang dihapus) tidak boleh menjadi URL yang 404 di browser,
     * karena gambar yang gagal memuat menghapus ruang yang sudah dipesan dan menggeser layout.
     */
    public static function existingUrl(?string $pathOnPublicDisk, ?Request $request = null): ?string
    {
        if ($pathOnPublicDisk === null || $pathOnPublicDisk === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $pathOnPublicDisk), '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::url($path, $request);
    }
}
