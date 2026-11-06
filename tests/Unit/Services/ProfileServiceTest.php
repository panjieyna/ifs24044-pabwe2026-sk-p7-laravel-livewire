<?php

namespace Tests\Unit\Services;

use App\Features\Profile\Services\ProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileService;
        Storage::fake('public');
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@test.com',
        ]);

        $result = $this->service->updateProfile($user, [
            'name' => 'New Name',
            'username' => 'new_user',
            'email' => 'new@test.com',
        ]);

        $this->assertTrue($result);
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new_user', $user->username);
        $this->assertEquals('new@test.com', $user->email);
    }

    public function test_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password'),
        ]);

        $result = $this->service->updatePassword($user, 'new_secret_123');
        $this->assertTrue($result);

        $user->refresh();
        $this->assertTrue(Hash::check('new_secret_123', $user->password));
    }

    public function test_update_profile_photo_with_valid_image(): void
    {
        $user = User::factory()->create();

        // Create a 100x50 dummy image with GD
        $img = imagecreatetruecolor(100, 50);
        imagefilledrectangle($img, 0, 0, 99, 49, imagecolorallocate($img, 255, 0, 0));
        ob_start();
        imagepng($img);
        $binary = ob_get_clean();

        $dataUri = 'data:image/png;base64,'.base64_encode($binary);

        $savedPath = $this->service->updateProfilePhoto($user, $dataUri);

        $this->assertNotEmpty($savedPath);
        $user->refresh();
        $this->assertEquals($savedPath, $user->profile_photo_path);
        Storage::disk('public')->assertExists($savedPath);

        // Delete photo
        $deleted = $this->service->deleteProfilePhoto($user);
        $this->assertTrue($deleted);
        $user->refresh();
        $this->assertNull($user->profile_photo_path);
        Storage::disk('public')->assertMissing($savedPath);
    }

    public function test_update_profile_photo_with_invalid_image_throws_exception(): void
    {
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format gambar tidak valid atau tidak didukung.');
        $this->service->updateProfilePhoto($user, 'not_a_valid_image_string');
    }

    public function test_raw_image_is_cropped_to_400_square_pixels_and_replaces_previous_photo(): void
    {
        $user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.png']);
        Storage::disk('public')->put('profile-photos/old.png', 'previous content');
        $image = UploadedFile::fake()->image('portrait.jpg', 80, 160);
        $path = $this->service->updateProfilePhoto($user, file_get_contents($image->getRealPath()));
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([400, 400], array_slice($dimensions, 0, 2));
        Storage::disk('public')->assertMissing('profile-photos/old.png');
    }

    /**
     * @param  list<array{int, int, int}>  $expectedCorners
     */
    #[DataProvider('jpegOrientations')]
    public function test_profile_photo_respects_jpeg_orientation(?int $orientation, array $expectedCorners): void
    {
        $user = User::factory()->create();

        $path = $this->service->updateProfilePhoto($user, $this->quadrantJpeg($orientation));

        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $user->fresh()->profile_photo_path);
        $binary = Storage::disk('public')->get($path);
        $dimensions = getimagesizefromstring($binary);
        $this->assertSame([400, 400, IMAGETYPE_PNG], array_slice($dimensions, 0, 3));

        $image = imagecreatefromstring($binary);
        foreach ([[40, 40], [360, 40], [40, 360], [360, 360]] as $corner => [$x, $y]) {
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            foreach (['red', 'green', 'blue'] as $channel => $name) {
                $this->assertEqualsWithDelta(
                    $expectedCorners[$corner][$channel],
                    $color[$name],
                    8,
                    "Unexpected {$name} channel at corner {$corner} for orientation ".($orientation ?? 'absent'),
                );
            }
        }
    }

    /**
     * @return array<string, array{?int, list<array{int, int, int}>}>
     */
    public static function jpegOrientations(): array
    {
        $red = [255, 0, 0];
        $green = [0, 255, 0];
        $blue = [0, 0, 255];
        $yellow = [255, 255, 0];

        return [
            'no EXIF orientation' => [null, [$red, $green, $blue, $yellow]],
            'normal' => [1, [$red, $green, $blue, $yellow]],
            'mirrored horizontally' => [2, [$green, $red, $yellow, $blue]],
            'rotated 180 degrees' => [3, [$yellow, $blue, $green, $red]],
            'mirrored vertically' => [4, [$blue, $yellow, $red, $green]],
            'transposed' => [5, [$red, $blue, $green, $yellow]],
            'rotated 90 degrees clockwise' => [6, [$blue, $red, $yellow, $green]],
            'transversed' => [7, [$yellow, $green, $blue, $red]],
            'rotated 90 degrees counterclockwise' => [8, [$green, $yellow, $red, $blue]],
        ];
    }

    private function quadrantJpeg(?int $orientation): string
    {
        $image = imagecreatetruecolor(200, 120);
        imagefilledrectangle($image, 0, 0, 99, 59, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 100, 0, 199, 59, imagecolorallocate($image, 0, 255, 0));
        imagefilledrectangle($image, 0, 60, 99, 119, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 100, 60, 199, 119, imagecolorallocate($image, 255, 255, 0));
        ob_start();
        imagejpeg($image, null, 100);
        $binary = ob_get_clean();

        if ($orientation === null) {
            return $binary;
        }

        $metadata = "Exif\0\0II".pack('v', 42).pack('V', 8)
            .pack('v', 1).pack('vvV', 0x0112, 3, 1)
            .pack('v', $orientation)."\0\0".pack('V', 0);

        return substr($binary, 0, 2)."\xFF\xE1".pack('n', strlen($metadata) + 2).$metadata.substr($binary, 2);
    }

    public function test_profile_service_does_not_read_filesystem_paths(): void
    {
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('private.png');
        $this->expectException(RuntimeException::class);
        $this->service->updateProfilePhoto($user, $image->getRealPath());
    }

    public function test_profile_service_rejects_invalid_base64(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gambar harus valid');
        $this->service->updateProfilePhoto(User::factory()->create(), 'data:image/png;base64,%%%');
    }

    public function test_profile_service_rejects_oversized_payloads(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->updateProfilePhoto(User::factory()->create(), str_repeat('x', 5 * 1024 * 1024 + 1));
    }

    public function test_profile_service_rejects_corrupt_image_with_recognizable_header(): void
    {
        $image = 'GIF89a'.pack('vv', 2, 2).str_repeat(chr(0), 20);
        $this->assertIsArray(getimagesizefromstring($image));
        $this->expectException(RuntimeException::class);
        $this->service->updateProfilePhoto(User::factory()->create(), $image);
    }
}
