<?php

namespace App\Support;

use App\Enums\ClaimType;
use App\Models\User;

/**
 * Pembagian modul pengajuan menjadi beberapa "bagian" yang berdiri sendiri di
 * antarmuka: Reimbursement, Pengadaan Barang, dan Layanan & Server.
 *
 * Di database semuanya tetap satu tabel `reimbursements` yang dibedakan kolom
 * claim_type — memecahnya jadi beberapa tabel hanya akan menggandakan alur
 * persetujuan dan pembayaran yang persis sama. Yang dipisah adalah cara
 * memakainya: masing-masing punya menu, alamat, daftar, dan form sendiri,
 * sehingga pengadaan tidak lagi bercampur di daftar Reimbursement.
 *
 * Kelas ini sumber tunggal pembagian itu — dipakai menu sidebar, route web,
 * penyaringan daftar (?section=), dan pilihan jenis di form.
 */
final class ClaimSection
{
    /**
     * @param  array<int, ClaimType>  $claimTypes
     * @param  array<int, string>  $permissions  any-of; kosong = terbuka untuk semua
     */
    private function __construct(
        public readonly string $key,
        public readonly string $path,
        public readonly string $label,
        public readonly string $description,
        public readonly array $claimTypes,
        public readonly array $permissions,
    ) {}

    /** @return array<int, self> */
    public static function all(): array
    {
        return [
            new self(
                key: 'reimbursement',
                path: '/reimbursements',
                label: 'Reimbursement',
                description: 'Penggantian biaya yang sudah Anda talangi, serta klaim lembur.',
                claimTypes: [ClaimType::Expense, ClaimType::Overtime],
                permissions: ['reimbursement.view', 'reimbursement.viewAny'],
            ),
            new self(
                key: 'goods',
                path: '/goods',
                label: 'Pengadaan Barang',
                description: 'Permintaan pembelian barang/aset: laptop, perangkat, ATK, sparepart.',
                claimTypes: [ClaimType::Goods],
                permissions: ['reimbursement.procurement'],
            ),
            new self(
                key: 'services',
                path: '/services',
                label: 'Layanan & Server',
                description: 'Penambahan atau perpanjangan layanan: server, cloud, domain, langganan.',
                claimTypes: [ClaimType::Service],
                permissions: ['reimbursement.procurement'],
            ),
        ];
    }

    public static function find(string $key): self
    {
        return self::tryFind($key) ?? throw new \InvalidArgumentException("Bagian pengajuan tidak dikenal: {$key}");
    }

    public static function tryFind(?string $key): ?self
    {
        if ($key === null || $key === '') {
            return null;
        }

        foreach (self::all() as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    /** Bagian tempat sebuah jenis pengajuan bernaung. */
    public static function forClaimType(ClaimType $type): self
    {
        foreach (self::all() as $section) {
            if (in_array($type, $section->claimTypes, true)) {
                return $section;
            }
        }

        return self::find('reimbursement');
    }

    /**
     * Jenis yang boleh dipakai user DI BAGIAN INI. Menggabungkan pembagian
     * menu dengan hak akses per jenis (lihat ClaimType::casesFor), supaya
     * pemilih jenis di form tidak pernah menawarkan yang tak boleh dipakai.
     *
     * @return array<int, ClaimType>
     */
    public function claimTypesFor(?User $user): array
    {
        $allowed = ClaimType::casesFor($user);

        return array_values(array_filter(
            $this->claimTypes,
            fn (ClaimType $type) => in_array($type, $allowed, true),
        ));
    }

    /** @return array<int, string> */
    public function claimTypeValues(): array
    {
        return array_map(fn (ClaimType $type) => $type->value, $this->claimTypes);
    }

    /** Jenis tunggal bagian ini, atau null bila memuat lebih dari satu. */
    public function lockedType(): ?ClaimType
    {
        return count($this->claimTypes) === 1 ? $this->claimTypes[0] : null;
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->permissions === [] || $user->hasRole('super_admin')) {
            return true;
        }

        foreach ($this->permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Payload untuk halaman React. */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'path' => $this->path,
            'label' => $this->label,
            'description' => $this->description,
            'claim_types' => $this->claimTypeValues(),
            'locked_type' => $this->lockedType()?->value,
        ];
    }
}
