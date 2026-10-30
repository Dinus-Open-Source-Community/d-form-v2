<?php

namespace App\Services\User;

use App\Models\User;
use App\Support\PublicStorage;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UserAvatarService
{
    /**
     * Unggah avatar baru: simpan di disk `public`, hapus file lokal sebelumnya bila ada.
     */
    public function update(User $user, UploadedFile $file): void
    {
        $previous = $user->avatar;

        $path = app(SafeUploadStorage::class)->store($file, UploadStorageTarget::public('avatars/'.$user->getKey()));

        if (self::isStoredRelativePath($previous)) {
            Storage::disk('public')->delete($previous);
        }

        $user->forceFill(['avatar' => $path])->save();
    }

    /**
     * Hapus avatar (URL OAuth atau path lokal). File lokal ikut dihapus dari storage.
     */
    public function clear(User $user): void
    {
        $previous = $user->avatar;

        if (self::isStoredRelativePath($previous)) {
            Storage::disk('public')->delete($previous);
        }

        $user->forceFill(['avatar' => null])->save();
    }

    /**
     * Nilai di DB: path relatif di disk `public`, atau URL absolut (OAuth).
     * Untuk Inertia / img src: path relatif dijadikan URL publik.
     */
    public static function resolvePublicUrl(?string $stored, ?Request $request = null): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (! self::isStoredRelativePath($stored)) {
            return $stored;
        }

        return PublicStorage::url($stored, $request);
    }

    /**
     * True bila nilai di DB adalah path relatif disk `public`, bukan URL absolut.
     *
     * @phpstan-assert-if-true string $value
     */
    private static function isStoredRelativePath(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return ! preg_match('#^https?://#i', $value);
    }
}
