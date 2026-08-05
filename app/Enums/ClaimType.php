<?php

namespace App\Enums;

use App\Models\User;

/**
 * Jenis pengajuan. Selain reimbursement biaya (uang sudah keluar duluan),
 * sistem juga menerima permintaan dana untuk pengadaan barang, penambahan
 * layanan/server, dan pembayaran lembur.
 *
 * Enum ini adalah SATU-SATUNYA sumber definisi field tambahan per jenis:
 * dipakai untuk membangun aturan validasi (backend) dan untuk merender form
 * dinamis (frontend, lewat GET /api/options/claim-types). Menambah jenis atau
 * field baru cukup dilakukan di sini.
 */
enum ClaimType: string
{
    case Expense = 'expense';
    case Goods = 'goods';
    case Service = 'service';
    case Overtime = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Reimbursement Biaya',
            self::Goods => 'Pengadaan Barang',
            self::Service => 'Layanan / Server',
            self::Overtime => 'Lembur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Expense => 'Biaya yang sudah Anda talangi lebih dulu (transport, konsumsi, dinas).',
            self::Goods => 'Permintaan pembelian barang/aset: laptop, perangkat, ATK, sparepart.',
            self::Service => 'Penambahan atau perpanjangan layanan: server, cloud, domain, langganan software.',
            self::Overtime => 'Klaim upah lembur berdasarkan jam kerja di luar jam normal.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Expense => '🧾',
            self::Goods => '📦',
            self::Service => '🖥️',
            self::Overtime => '⏰',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Expense => 'gray',
            self::Goods => 'indigo',
            self::Service => 'blue',
            self::Overtime => 'amber',
        };
    }

    /** Permission yang harus dimiliki untuk memakai jenis ini; null = bebas. */
    public function requiredPermission(): ?string
    {
        return match ($this) {
            // Penggantian biaya: hak paling dasar, semua pengaju (termasuk Magang).
            self::Expense => null,
            // Lembur: Employee ke atas, tidak untuk Magang.
            self::Overtime => 'reimbursement.overtime',
            // Pengadaan barang/layanan: Supervisor ke atas, tidak untuk Employee.
            self::Goods, self::Service => 'reimbursement.procurement',
        };
    }

    public function allowedFor(?User $user): bool
    {
        $permission = $this->requiredPermission();

        if ($permission === null) {
            return true;
        }

        return (bool) $user?->hasPermission($permission);
    }

    /**
     * Jenis pengajuan yang boleh dipakai user ini. Dipakai untuk menyusun
     * pilihan di form sekaligus daftar nilai yang sah saat validasi, agar
     * tampilan dan aturan tidak pernah berbeda.
     *
     * @return array<int, self>
     */
    public static function casesFor(?User $user): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type->allowedFor($user),
        ));
    }

    /**
     * Field tambahan yang diisi user untuk jenis ini.
     *
     * Tiap field: key, label, type (text|textarea|number|date|time|select),
     * required, dan atribut opsional (help, suffix, options, step, min).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Expense => [],

            self::Goods => [
                ['key' => 'item_name', 'label' => 'Nama Barang', 'type' => 'text', 'required' => true,
                    'help' => 'Contoh: Server Dell PowerEdge R650, Laptop ThinkPad T14'],
                ['key' => 'specification', 'label' => 'Spesifikasi', 'type' => 'textarea', 'required' => false,
                    'help' => 'Detail teknis: prosesor, RAM, kapasitas, tipe, warna, dll.'],
                ['key' => 'quantity', 'label' => 'Jumlah', 'type' => 'number', 'required' => true, 'min' => 1, 'suffix' => 'unit'],
                ['key' => 'unit_price', 'label' => 'Harga Satuan', 'type' => 'number', 'required' => true, 'min' => 1, 'suffix' => 'Rp'],
                ['key' => 'vendor', 'label' => 'Vendor / Toko', 'type' => 'text', 'required' => false],
                ['key' => 'needed_by', 'label' => 'Dibutuhkan Sebelum', 'type' => 'date', 'required' => false],
                ['key' => 'urgency', 'label' => 'Urgensi', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'urgent', 'label' => 'Mendesak'],
                    ['value' => 'critical', 'label' => 'Kritis (menghambat operasional)'],
                ]],
            ],

            self::Service => [
                ['key' => 'service_name', 'label' => 'Nama Layanan', 'type' => 'text', 'required' => true,
                    'help' => 'Contoh: Penambahan server produksi, Perpanjangan domain, Lisensi Figma'],
                ['key' => 'provider', 'label' => 'Penyedia', 'type' => 'text', 'required' => false,
                    'help' => 'Contoh: AWS, Biznet, Niagahoster, Google Workspace'],
                ['key' => 'specification', 'label' => 'Spesifikasi / Paket', 'type' => 'textarea', 'required' => false,
                    'help' => 'Contoh: 8 vCPU, 16 GB RAM, 500 GB SSD, region Jakarta'],
                ['key' => 'billing_cycle', 'label' => 'Siklus Tagihan', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'one_time', 'label' => 'Sekali Bayar'],
                    ['value' => 'monthly', 'label' => 'Bulanan'],
                    ['value' => 'yearly', 'label' => 'Tahunan'],
                ]],
                ['key' => 'quantity', 'label' => 'Jumlah Periode / Unit', 'type' => 'number', 'required' => true, 'min' => 1,
                    'help' => 'Berapa bulan/tahun/unit yang diajukan.'],
                ['key' => 'unit_price', 'label' => 'Biaya per Periode / Unit', 'type' => 'number', 'required' => true, 'min' => 1, 'suffix' => 'Rp'],
                ['key' => 'period_start', 'label' => 'Mulai Berlaku', 'type' => 'date', 'required' => false],
                ['key' => 'period_end', 'label' => 'Berakhir', 'type' => 'date', 'required' => false],
            ],

            self::Overtime => [
                ['key' => 'overtime_date', 'label' => 'Tanggal Lembur', 'type' => 'date', 'required' => true],
                ['key' => 'start_time', 'label' => 'Jam Mulai', 'type' => 'time', 'required' => true],
                ['key' => 'end_time', 'label' => 'Jam Selesai', 'type' => 'time', 'required' => true],
                ['key' => 'hours', 'label' => 'Total Jam', 'type' => 'number', 'required' => true, 'min' => 0.5, 'step' => 0.5, 'suffix' => 'jam'],
                // Tarif mengikuti jabatan, bukan isian bebas. Diisi server dari
                // roles.overtime_rate (lihat serverSourcedFields()) dan
                // disembunyikan dari tampilan: besaran upah per jabatan bukan
                // informasi yang perlu beredar di form pengajuan. Nominal
                // total tetap terlihat.
                ['key' => 'hourly_rate', 'label' => 'Upah per Jam', 'type' => 'number', 'required' => true,
                    'min' => 1, 'suffix' => 'Rp', 'source' => 'overtime_rate', 'hidden' => true],
                ['key' => 'work_description', 'label' => 'Pekerjaan yang Dikerjakan', 'type' => 'textarea', 'required' => true],
            ],
        };
    }

    /**
     * Field yang nilainya DITENTUKAN SERVER dari profil user, bukan diisi
     * sendiri oleh pengaju. Bentuknya [key field => sumber nilainya].
     *
     * Upah lembur mengikuti jabatan; kalau dibiarkan sebagai isian bebas,
     * siapa pun bisa mengetik tarif berapa pun dan langsung menaikkan nominal
     * klaimnya.
     *
     * @return array<string, string>
     */
    public function serverSourcedFields(): array
    {
        return match ($this) {
            self::Overtime => ['hourly_rate' => 'overtime_rate'],
            default => [],
        };
    }

    /**
     * Dua field yang dikalikan untuk memperoleh nominal otomatis.
     * Null berarti nominal diisi manual oleh user.
     *
     * @return array{0: string, 1: string}|null
     */
    public function amountFormula(): ?array
    {
        return match ($this) {
            self::Expense => null,
            self::Goods, self::Service => ['quantity', 'unit_price'],
            self::Overtime => ['hours', 'hourly_rate'],
        };
    }

    /** Hitung nominal dari detail; null bila jenis ini nominalnya manual/tak lengkap. */
    public function computeAmount(array $details): ?int
    {
        $formula = $this->amountFormula();

        if (! $formula) {
            return null;
        }

        [$a, $b] = $formula;

        if (! is_numeric($details[$a] ?? null) || ! is_numeric($details[$b] ?? null)) {
            return null;
        }

        return (int) round((float) $details[$a] * (float) $details[$b]);
    }

    /** Nominal jenis ini dihitung sistem (field amount di-lock di UI). */
    public function hasComputedAmount(): bool
    {
        return $this->amountFormula() !== null;
    }

    /** Aturan validasi Laravel untuk `details.*` sesuai definisi field. */
    public function validationRules(bool $optional = false): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rule = [$field['required'] && ! $optional ? 'required' : 'nullable'];

            $rule[] = match ($field['type']) {
                'number' => 'numeric',
                'date' => 'date',
                'time' => 'date_format:H:i',
                'select' => 'string',
                default => 'string',
            };

            if ($field['type'] === 'number' && isset($field['min'])) {
                $rule[] = 'min:'.$field['min'];
            }

            if ($field['type'] === 'select') {
                $rule[] = 'in:'.implode(',', array_column($field['options'], 'value'));
            }

            if (in_array($field['type'], ['text', 'textarea'], true)) {
                $rule[] = 'max:'.($field['type'] === 'text' ? 150 : 2000);
            }

            $rules["details.{$field['key']}"] = $rule;
        }

        return $rules;
    }

    /**
     * Ubah detail mentah menjadi pasangan label→nilai siap tampil.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function displayDetails(?array $details): array
    {
        if (! $details) {
            return [];
        }

        $rows = [];

        foreach ($this->fields() as $field) {
            // Field tersembunyi (mis. upah lembur per jam) tidak ditampilkan di
            // mana pun — form maupun halaman detail pengajuan.
            if ($field['hidden'] ?? false) {
                continue;
            }

            $value = $details[$field['key']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ($field['type'] === 'select') {
                $match = collect($field['options'])->firstWhere('value', $value);
                $value = $match['label'] ?? $value;
            } elseif (($field['suffix'] ?? null) === 'Rp') {
                $value = 'Rp '.number_format((float) $value, 0, ',', '.');
            } elseif (isset($field['suffix'])) {
                $value = $value.' '.$field['suffix'];
            }

            $rows[] = ['label' => $field['label'], 'value' => (string) $value];
        }

        return $rows;
    }

    /**
     * Payload untuk form dinamis di frontend.
     *
     * Field bersumber-server diberi `fixed_value` sesuai profil user, supaya
     * form bisa menampilkannya terkunci beserta angkanya (mis. upah lembur
     * sesuai jabatan) tanpa frontend perlu tahu aturannya.
     */
    public function toOption(?User $user = null): array
    {
        $fields = array_map(function (array $field) use ($user) {
            $source = $field['source'] ?? null;

            if ($source !== null) {
                $field['readonly'] = true;
                $field['fixed_value'] = self::resolveSourcedValue($source, $user);
            }

            return $field;
        }, $this->fields());

        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'fields' => $fields,
            'amount_formula' => $this->amountFormula(),
        ];
    }

    /** Ambil nilai field bersumber-server dari profil user. */
    public static function resolveSourcedValue(string $source, ?User $user): ?int
    {
        return match ($source) {
            'overtime_rate' => $user?->overtimeRate(),
            default => null,
        };
    }

    /**
     * Opsi form, hanya berisi jenis yang boleh dipakai user tersebut.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(?User $user = null): array
    {
        return array_map(fn (self $type) => $type->toOption($user), self::casesFor($user));
    }
}
