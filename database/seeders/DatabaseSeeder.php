<?php

namespace Database\Seeders;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrator', 'email' => 'admin@example.com',
            'password' => Hash::make('admin'), 'role' => 'admin',
        ]);

        $member = User::firstOrCreate(['username' => 'member'], [
            'name' => 'Member User', 'email' => 'member@example.com',
            'password' => Hash::make('member'), 'role' => 'member',
        ]);

        $items = [
            ['name' => 'Keyboard Mekanik', 'sku' => 'ELK-001', 'quantity' => 24, 'unit' => 'pcs', 'price' => 450000, 'min_stock' => 5, 'description' => "## Keyboard mekanik\n\nKoneksi **USB-C**, tata letak 75%, dan switch taktil.\n\n- Simpan di tempat kering\n- Periksa kelengkapan kabel sebelum distribusi"],
            ['name' => 'Mouse Nirkabel', 'sku' => 'ELK-002', 'quantity' => 4, 'unit' => 'pcs', 'price' => 175000, 'min_stock' => 5, 'description' => 'Mouse ergonomis dengan koneksi Bluetooth dan receiver USB.'],
            ['name' => 'Kertas A4 80 gsm', 'sku' => 'ATK-001', 'quantity' => 0, 'unit' => 'rim', 'price' => 65000, 'min_stock' => 3, 'description' => 'Kertas putih A4 untuk kebutuhan administrasi. **Perlu restok.**'],
            ['name' => 'Buku Catatan', 'sku' => 'ATK-002', 'quantity' => 48, 'unit' => 'pcs', 'price' => 25000, 'min_stock' => 10, 'description' => 'Buku catatan hardcover ukuran A5, 100 lembar.'],
            ['name' => 'Kabel USB-C', 'sku' => 'ELK-003', 'quantity' => 15, 'unit' => 'pcs', 'price' => 85000, 'min_stock' => 5, 'description' => 'Kabel USB-C 1 meter untuk pengisian daya dan transfer data.'],
            ['name' => 'Tinta Printer Hitam', 'sku' => 'ATK-003', 'quantity' => 2, 'unit' => 'botol', 'price' => 120000, 'min_stock' => 3, 'description' => 'Tinta printer hitam 70 ml. Periksa kompatibilitas sebelum penggunaan.'],
        ];

        foreach ($items as $item) {
            Stock::firstOrCreate(['user_id' => $member->id, 'sku' => $item['sku']], $item);
        }
    }
}
