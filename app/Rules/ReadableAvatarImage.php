<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReadableAvatarImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // SplFileInfo::getRealPath() can be false for an otherwise readable upload.
        // Check the actual temporary pathname before any image/file operation.
        $path = $value instanceof UploadedFile ? $value->getPathname() : '';
        if (trim($path) === '' || str_contains($path, "\0") || ! $value->isValid() || ! is_file($path) || ! is_readable($path)) {
            $fail('Không đọc được ảnh tải lên. Vui lòng chọn lại ảnh.');

            return;
        }

        $dimensions = @getimagesize($path);
        if (! $dimensions) {
            $fail('Ảnh đại diện phải là ảnh JPG, PNG hoặc WebP hợp lệ.');
        } elseif ($dimensions[0] > 4096 || $dimensions[1] > 4096) {
            $fail('Kích thước ảnh đại diện không được vượt quá 4096 × 4096 px.');
        }
    }
}
