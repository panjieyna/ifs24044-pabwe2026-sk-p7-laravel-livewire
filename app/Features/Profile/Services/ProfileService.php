<?php

namespace App\Features\Profile\Services;

use App\Models\User;
use GdImage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProfileService
{
    /**
     * Update user personal information.
     *
     * @param  array{name: string, username: string, email: string}  $data
     */
    public function updateProfile(User $user, array $data): bool
    {
        return $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(User $user, string $newPassword): bool
    {
        return $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    /**
     * Crop image to 1:1 ratio and save as user's profile photo.
     *
     * @param  string  $imageData  Base64 data URI or raw image contents
     */
    public function updateProfilePhoto(User $user, string $imageData): string
    {
        if (str_contains($imageData, ';base64,')) {
            [, $content] = explode(';base64,', $imageData, 2);
            $binary = base64_decode($content, true);
        } else {
            $binary = $imageData;
        }

        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            throw new RuntimeException('Gambar harus valid dan berukuran maksimal 5 MB.');
        }

        $dimensions = @getimagesizefromstring($binary);
        if ($dimensions === false || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
            throw new RuntimeException('Format gambar tidak valid atau tidak didukung.');
        }

        $sourceImage = @imagecreatefromstring($binary);
        if (! $sourceImage) {
            throw new RuntimeException('Format gambar tidak valid atau tidak didukung.');
        }

        $sourceImage = $this->normalizeImageOrientation($sourceImage, $binary, $dimensions[2]);

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Calculate center square crop coordinates
        $cropSize = min($origWidth, $origHeight);
        $srcX = (int) (($origWidth - $cropSize) / 2);
        $srcY = (int) (($origHeight - $cropSize) / 2);

        $targetSize = 400; // Standard 1:1 avatar resolution
        $croppedCanvas = imagecreatetruecolor($targetSize, $targetSize);

        // Preserve PNG transparency
        imagealphablending($croppedCanvas, false);
        imagesavealpha($croppedCanvas, true);

        imagecopyresampled(
            $croppedCanvas,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $cropSize,
            $cropSize
        );

        ob_start();
        imagepng($croppedCanvas, null, 8);
        $outputBinary = ob_get_clean();

        // Delete old photo if present
        $this->deleteProfilePhoto($user);

        $filename = 'profile-photos/'.Str::random(40).'.png';
        Storage::disk('public')->put($filename, $outputBinary);

        $user->update(['profile_photo_path' => $filename]);

        return $filename;
    }

    /**
     * Apply camera orientation before the image is re-encoded without EXIF metadata.
     */
    private function normalizeImageOrientation(GdImage $image, string $binary, int $imageType): GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG) {
            return $image;
        }

        $stream = fopen('php://memory', 'w+b');
        try {
            fwrite($stream, $binary);
            rewind($stream);
            $metadata = @exif_read_data($stream);
        } finally {
            fclose($stream);
        }

        $orientation = (int) ($metadata['Orientation'] ?? 1);

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $rotation = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        return $rotation === 0 ? $image : imagerotate($image, $rotation, 0);
    }

    /**
     * Delete existing profile photo.
     */
    public function deleteProfilePhoto(User $user): bool
    {
        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        return $user->update(['profile_photo_path' => null]);
    }
}
