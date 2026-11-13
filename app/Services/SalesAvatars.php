<?php

namespace App\Services;

use App\Rules\ReadableAvatarImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SalesAvatars
{
    public static function rules(): array
    {
        return ['bail', 'nullable', new ReadableAvatarImage, 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
            'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    public function store(string $source, string $disk = 'sales_avatars', int $maxEdge = 512, bool $generatedDemo = false): string
    {
        if (trim($source) === '' || str_contains($source, "\0") || ! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException('Cannot read avatar upload.');
        }
        $bytes = file_get_contents($source);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Cannot read avatar upload.');
        }
        // Re-encode raster uploads, strip metadata and keep private thumbnails small.
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('Cannot decode avatar image.');
        }
        $scale = min(1, $maxEdge / max(imagesx($image), imagesy($image)));
        $thumbnail = imagecreatetruecolor(max(1, (int) (imagesx($image) * $scale)), max(1, (int) (imagesy($image) * $scale)));
        imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 245, 245, 244));
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, imagesx($thumbnail), imagesy($thumbnail), imagesx($image), imagesy($image));
        ob_start();
        try {
            if (! imagejpeg($thumbnail, null, 88)) {
                throw new RuntimeException('Cannot encode avatar image.');
            }
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $path = ($generatedDemo ? 'demo-' : 'uploaded-').Str::uuid().'.jpg';
        try {
            if (! Storage::disk($disk)->put($path, $contents)) {
                throw new RuntimeException('Cannot store avatar image.');
            }
        } catch (Throwable $exception) {
            // A failed adapter may have written part of the new file; never touch the old one.
            rescue(fn () => $this->deleteManaged($path, $disk));
            throw $exception;
        }

        return $path;
    }

    public function deleteManaged(?string $path, string $disk = 'sales_avatars'): void
    {
        if (! in_array($disk, ['sales_avatars', 'dealer_avatars'], true)
            || $path === null
            || ! preg_match('/\A(?:uploaded-|demo-)[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.jpg\z/iD', $path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }
}
