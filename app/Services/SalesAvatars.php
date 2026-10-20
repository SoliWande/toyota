<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SalesAvatars
{
    public static function rules(): array
    {
        return ['bail', 'nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
            'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'];
    }

    public function store(string $source, string $disk = 'sales_avatars', int $maxEdge = 512, bool $generatedDemo = false): string
    {
        // Re-encode raster uploads, strip metadata and keep private thumbnails small.
        $image = imagecreatefromstring(file_get_contents($source));
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
        Storage::disk($disk)->put($path, $contents);

        return $path;
    }
}
