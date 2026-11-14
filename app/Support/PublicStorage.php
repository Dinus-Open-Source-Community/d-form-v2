<?php

namespace App\Support;

use Illuminate\Http\Request;

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
}
