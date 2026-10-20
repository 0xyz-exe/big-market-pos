<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            'branches.access_all' => 'Akses semua cabang',
            'branches.manage' => 'Kelola cabang',
            'products.view' => 'Lihat katalog produk',
            'products.manage' => 'Kelola katalog produk',
            'inventory.view' => 'Lihat persediaan',
            'inventory.adjust' => 'Sesuaikan persediaan',
            'inventory.transfer' => 'Transfer persediaan',
            'purchases.view' => 'Lihat pembelian',
            'purchases.manage' => 'Kelola pesanan pembelian',
            'purchases.receive' => 'Terima barang',
            'sales.create' => 'Buat penjualan',
            'sales.view' => 'Lihat penjualan',
            'sales.discount' => 'Terapkan diskon penjualan',
            'sales.cancel' => 'Batalkan penjualan',
            'refunds.create' => 'Ajukan pengembalian dana',
            'refunds.approve' => 'Setujui pengembalian dana',
            'shifts.manage' => 'Kelola shift kasir',
            'shifts.close_all' => 'Tutup shift kasir lain',
            'expenses.manage' => 'Kelola pengeluaran',
            'expenses.approve' => 'Setujui atau tolak pengeluaran',
            'reports.sales' => 'Lihat laporan penjualan',
            'reports.financial' => 'Lihat laporan keuangan',
            'users.manage' => 'Kelola pengguna dan peran',
            'audit.view' => 'Lihat log audit',
        ];

        $permissions = collect($labels)->mapWithKeys(fn (string $label, string $name) => [
            $name => Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]),
        ]);

        $rolePermissions = [
            'super_admin' => array_keys($labels),
            'owner' => array_keys($labels),
            'branch_manager' => [
                'branches.manage', 'products.view', 'products.manage', 'inventory.view',
                'inventory.adjust', 'inventory.transfer', 'purchases.view', 'purchases.manage',
                'purchases.receive', 'sales.view', 'sales.discount', 'sales.cancel', 'refunds.create',
                'refunds.approve', 'shifts.manage', 'shifts.close_all', 'expenses.manage', 'expenses.approve', 'reports.sales',
                'reports.financial', 'audit.view',
            ],
            'cashier' => ['products.view', 'sales.create', 'sales.view', 'shifts.manage'],
            'inventory_staff' => [
                'products.view', 'inventory.view', 'inventory.adjust', 'inventory.transfer',
                'purchases.view', 'purchases.receive',
            ],
            'purchasing_staff' => ['products.view', 'purchases.view', 'purchases.manage', 'purchases.receive'],
            'accountant' => [
                'sales.view', 'purchases.view', 'expenses.manage', 'expenses.approve', 'reports.sales',
                'reports.financial', 'audit.view',
            ],
        ];

        $roleLabels = [
            'super_admin' => 'Super Admin',
            'owner' => 'Pemilik',
            'branch_manager' => 'Manajer Cabang',
            'cashier' => 'Kasir',
            'inventory_staff' => 'Petugas Persediaan',
            'purchasing_staff' => 'Petugas Pembelian',
            'accountant' => 'Akuntan',
        ];

        foreach ($rolePermissions as $name => $permissionNames) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $roleLabels[$name]],
            );

            $role->permissions()->sync(
                collect($permissionNames)->map(fn (string $permission) => $permissions[$permission]->id)->all(),
            );
        }
    }
}
