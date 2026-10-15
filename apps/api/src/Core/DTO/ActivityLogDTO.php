<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

/**
 * Satu baris jejak audit siap tampil. Nama aksi diterjemahkan ke bahasa
 * Indonesia bila dikenali; aksi baru yang belum terpetakan tetap ditampilkan apa
 * adanya supaya jejaknya tidak pernah hilang.
 *
 * Catatan: label modul lain (user.*, category.*, dan seterusnya) mengikuti nama
 * aksi yang benar-benar ditulis ActivityLogger di Service masing-masing.
 */
final readonly class ActivityLogDTO
{
    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'create' => 'Menambah data',
        'update' => 'Memperbarui data',
        'delete' => 'Menghapus data',
        'check_out' => 'Serah terima keluar',
        'check_in' => 'Serah terima masuk',
        'receive' => 'Menerima pengadaan',
        'cancel' => 'Membatalkan data',
        'complete' => 'Menyelesaikan data',
        'return' => 'Pengembalian',
        'stock_in' => 'Stok masuk',
        'stock_out' => 'Stok keluar',
        'stock_adjustment' => 'Koreksi stok',
        'export' => 'Ekspor laporan',
        'login' => 'Masuk sistem',
        'login_failed' => 'Gagal masuk',
        'logout' => 'Keluar sistem',
        'user.create' => 'Menambah pengguna',
        'user.update' => 'Memperbarui pengguna',
        'user.delete' => 'Menghapus pengguna',
        'user.activate' => 'Mengaktifkan pengguna',
        'user.deactivate' => 'Menonaktifkan pengguna',
        'user.password_reset' => 'Mengatur ulang kata sandi',
        'category.create' => 'Menambah kategori',
        'category.update' => 'Memperbarui kategori',
        'category.delete' => 'Menghapus kategori',
        'location.create' => 'Menambah lokasi',
        'location.update' => 'Memperbarui lokasi',
        'location.delete' => 'Menghapus lokasi',
        'supplier.create' => 'Menambah supplier',
        'supplier.update' => 'Memperbarui supplier',
        'supplier.delete' => 'Menghapus supplier',
        'employee.create' => 'Menambah karyawan',
        'employee.update' => 'Memperbarui karyawan',
        'employee.delete' => 'Menghapus karyawan',
    ];

    public function __construct(
        public int $id,
        public ?int $userId,
        public ?string $userName,
        public string $action,
        public string $actionLabel,
        public ?string $subjectType,
        public ?int $subjectId,
        public string $description,
        public ?string $ipAddress,
        public string $createdAt,
        public string $createdAtFormatted,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $action = (string)($data['action'] ?? '');
        $createdAt = (string)($data['created_at'] ?? '');

        return new self(
            id: (int)($data['id'] ?? 0),
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            userName: !empty($data['user_name']) ? (string)$data['user_name'] : null,
            action: $action,
            actionLabel: self::ACTION_LABELS[$action] ?? $action,
            subjectType: !empty($data['subject_type']) ? (string)$data['subject_type'] : null,
            subjectId: isset($data['subject_id']) ? (int)$data['subject_id'] : null,
            description: (string)($data['description'] ?? ''),
            ipAddress: !empty($data['ip_address']) ? (string)$data['ip_address'] : null,
            createdAt: $createdAt,
            createdAtFormatted: self::formatCreatedAt($createdAt),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'action' => $this->action,
            'action_label' => $this->actionLabel,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'description' => $this->description,
            'ip_address' => $this->ipAddress,
            'created_at' => $this->createdAt,
            'created_at_formatted' => $this->createdAtFormatted,
        ];
    }

    /**
     * Contoh keluaran: "15 Okt 2026 12:30".
     */
    private static function formatCreatedAt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d', $timestamp)
            . ' ' . self::MONTHS[(int)date('n', $timestamp)]
            . ' ' . date('Y H:i', $timestamp);
    }
}
