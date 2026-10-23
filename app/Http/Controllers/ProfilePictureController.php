<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FileStore;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves profile pictures through the application (login.session only), so
 * they work without the public/storage symlink and after a database restore:
 * FileStore falls back to the stored_files copy when the disk file is gone.
 */
class ProfilePictureController extends Controller
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

    /**
     * Avatar URL for an account, or null when it has no picture. The ?v= part
     * changes with the picture path, so a new picture is never served from cache.
     */
    public static function urlFor(int|string|null $userId, ?string $path): ?string
    {
        if ($userId === null || $userId === '' || ! filled($path)) {
            return null;
        }

        return route('profile-pictures.show', ['user' => (int) $userId, 'v' => substr(sha1((string) $path), 0, 10)]);
    }

    public function show(User $user, FileStore $files): Response
    {
        $path = (string) $user->profile_picture;
        abort_unless($user->is_verified && str_starts_with($path, 'profile-pictures/') && ! str_contains($path, '..'), 404);

        $mime = $files->mime('public', $path);
        if ($mime === null && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path) ?: null;
        }
        abort_unless(in_array($mime, self::IMAGE_TYPES, true), 404);

        $response = $files->response('public', $path, basename($path), $mime, 'inline');
        abort_if($response === null, 404);

        // The URL carries a version of the path, so browsers may keep it for a day.
        $response->headers->set('Cache-Control', 'private, max-age=86400');

        return $response;
    }
}
