<?php

namespace Tests\Unit\Services;

use App\Features\Profile\Services\ProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
