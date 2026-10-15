<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MovementType;
use PDO;

final class AssetRepository
{
    /**
     * Kolom aset yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya
     * eksplisit dan setiap kolom baru hasil registrasi wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        a.id, a.asset_code, a.name, a.category_id, a.location_id, a.serial_number,
        a.purchase_date, a.purchase_cost, a.status, a.condition_level, a.assigned_to,
        a.notes, a.item_id, a.acquisition_source, a.purchase_reference,
        a.warranty_expiry, a.photo_path, a.document_path, a.created_at, a.updated_at
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search, category_id, status, location_id, item_id.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = '
            SELECT ' . self::COLUMNS . ',
                c.name AS category_name,
                c.code AS category_code,
                l.name AS location_name,
                l.code AS location_code
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN locations l ON a.location_id = l.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ? OR a.assigned_to LIKE ?)';
            $searchVal = '%' . $filters['search'] . '%';
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND a.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['location_id'])) {
            $sql .= ' AND a.location_id = ?';
            $params[] = (int)$filters['location_id'];
        }

        if (!empty($filters['item_id'])) {
            $sql .= ' AND a.item_id = ?';
            $params[] = (int)$filters['item_id'];
        }

        $sql .= ' ORDER BY a.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = '
            SELECT ' . self::COLUMNS . ',
                c.name AS category_name,
                l.name AS location_name,
                i.name AS item_name
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN locations l ON a.location_id = l.id
            LEFT JOIN items i ON i.id = a.item_id
            WHERE a.id = ?
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(AssetDTO $dto): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO assets (
                asset_code, name, category_id, item_id, location_id, serial_number,
                acquisition_source, purchase_reference, warranty_expiry,
                purchase_date, purchase_cost, status, condition_level, assigned_to,
                notes, photo_path, document_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $dto->assetCode,
            $dto->name,
            $dto->categoryId,
            $dto->itemId,
            $dto->locationId,
            $dto->serialNumber,
            $dto->acquisitionSource->value,
            $dto->purchaseReference,
            $dto->warrantyExpiry,
            $dto->purchaseDate,
            $dto->purchaseCost,
            $dto->status->value,
            $dto->conditionLevel->value,
            $dto->assignedTo,
            $dto->notes,
            $dto->photoPath,
            $dto->documentPath,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Memperbarui data aset. assigned_to sengaja tidak disentuh: pemegang hanya
     * berubah lewat serah terima, pengembalian, atau penempatan.
     */
    public function update(int $id, AssetDTO $dto): bool
    {
        $stmt = $this->db->prepare('
            UPDATE assets SET
                asset_code = ?,
                name = ?,
                category_id = ?,
                item_id = ?,
                location_id = ?,
                serial_number = ?,
                acquisition_source = ?,
                purchase_reference = ?,
                warranty_expiry = ?,
                purchase_date = ?,
                purchase_cost = ?,
                status = ?,
                condition_level = ?,
                notes = ?,
                photo_path = ?,
                document_path = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $dto->assetCode,
            $dto->name,
            $dto->categoryId,
            $dto->itemId,
            $dto->locationId,
            $dto->serialNumber,
            $dto->acquisitionSource->value,
            $dto->purchaseReference,
            $dto->warrantyExpiry,
            $dto->purchaseDate,
            $dto->purchaseCost,
            $dto->status->value,
            $dto->conditionLevel->value,
            $dto->notes,
            $dto->photoPath,
            $dto->documentPath,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM assets WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Menautkan unit aset hasil penerimaan ke pembelian dan baris pesanannya.
     * Kolomnya tidak ikut dibaca daftar/detail aset, jadi tidak mengubah kontrak
     * respons; nilainya murni jejak asal-usul.
     */
    public function linkPurchase(int $assetId, int $purchaseId, int $purchaseDetailId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE assets SET purchase_id = ?, purchase_detail_id = ?
            WHERE id = ?
        ');
        return $stmt->execute([$purchaseId, $purchaseDetailId, $assetId]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM assets WHERE asset_code = ?';
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Jumlah kode aset yang berawalan $prefix, dipakai untuk melanjutkan nomor
     * urut pembangkitan kode otomatis. Karakter wildcard LIKE di prefix di-escape
     * supaya kode jenis barang yang memuat "_" tidak ikut mencocokkan berlebih.
     */
    public function countByCodePrefix(string $prefix): int
    {
        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM assets WHERE asset_code LIKE ? ESCAPE '\\'");
        $stmt->execute([$pattern]);
        return (int)$stmt->fetchColumn();
    }

    public function categoryExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function locationExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function itemExists(int $id): bool
    {
        return $this->item($id) !== null;
    }

    /**
     * Baris jenis barang seperlunya: kode untuk prefix kode aset dan kategori
     * untuk mengisi category_id saat aset ditautkan ke jenis barang.
     *
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, code, name, category_id FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Mencatat hasil serah terima: status, pemegang, dan lokasi baru aset, plus
     * satu baris pergerakan. Repository hanya menuliskan keputusan yang diberikan
     * pemanggil — aturan transisi status ada di CheckOutAssetAction dan transaksi
     * dipegang Service lewat Connection::transaction().
     *
     * Actor, status, dan lokasi asal wajib diisi pemanggil: ketiganya menentukan
     * isi catatan audit sehingga tidak boleh punya default tersembunyi.
     *
     * @param AssetStatus $status Status hasil keputusan Action.
     * @param int|null $fromLocationId Lokasi asal untuk catatan pergerakan.
     * @param string|null $notes Catatan bebas pemanggil.
     */
    public function checkOut(
        int $assetId,
        string $recipient,
        int $targetLocationId,
        string $actor,
        AssetStatus $status,
        ?int $fromLocationId,
        ?string $notes = null
    ): bool {
        $stmt = $this->db->prepare('
            UPDATE assets
            SET status = ?, assigned_to = ?, location_id = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $status->value,
            $recipient,
            $targetLocationId,
            $assetId,
        ]);

        $this->recordMovement(
            $assetId,
            MovementType::CheckOut,
            $fromLocationId,
            $targetLocationId,
            $actor,
            $recipient,
            $notes
        );

        return true;
    }

    /**
     * Mencatat pengembalian aset: status dan lokasi baru, pemegang dilepas, plus
     * satu baris pergerakan dengan pemegang sebelumnya sebagai penerima.
     *
     * @param AssetStatus $status Status hasil keputusan Action.
     * @param int|null $fromLocationId Lokasi asal untuk catatan pergerakan.
     * @param string|null $previousHolder Pemegang saat aset masih di luar.
     * @param string|null $notes Catatan bebas pemanggil.
     */
    public function checkIn(
        int $assetId,
        int $returnLocationId,
        string $actor,
        AssetStatus $status,
        ?int $fromLocationId,
        ?string $previousHolder,
        ?string $notes = null
    ): bool {
        $stmt = $this->db->prepare('
            UPDATE assets
            SET status = ?, assigned_to = NULL, location_id = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $status->value,
            $returnLocationId,
            $assetId,
        ]);

        $this->recordMovement(
            $assetId,
            MovementType::CheckIn,
            $fromLocationId,
            $returnLocationId,
            $actor,
            $previousHolder,
            $notes
        );

        return true;
    }

    /**
     * Menetapkan pemegang, lokasi, dan status aset sekaligus, tanpa mencatat
     * pergerakan: dipakai transaksi penitipan yang memutuskan sendiri catatan
     * mutasinya. location_id null berarti lokasi aset tidak diubah (kolomnya NOT
     * NULL dan peminjaman sementara boleh tidak menyebut lokasi pemakaian).
     *
     * employee_id diterima untuk simetri pemanggil dengan baris penempatan;
     * tabel assets menyimpan nama pemegang (assigned_to), bukan id karyawan.
     */
    public function setHolder(
        int $assetId,
        ?int $employeeId,
        ?string $holderName,
        ?int $locationId,
        AssetStatus $status
    ): bool {
        $sets = ['status = ?', 'assigned_to = ?'];
        $params = [$status->value, $holderName];

        if ($locationId !== null) {
            $sets[] = 'location_id = ?';
            $params[] = $locationId;
        }

        $params[] = $assetId;
        $stmt = $this->db->prepare('UPDATE assets SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $stmt->execute($params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $stmt = $this->db->query('
            SELECT
                COUNT(*) AS total_assets,
                SUM(purchase_cost) AS total_valuation,
                SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) AS count_available,
                SUM(CASE WHEN status = "in_use" THEN 1 ELSE 0 END) AS count_in_use,
                SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) AS count_maintenance,
                SUM(CASE WHEN status = "disposed" THEN 1 ELSE 0 END) AS count_disposed
            FROM assets
        ');
        $row = $stmt->fetch() ?: [];

        return [
            'total_assets' => (int)($row['total_assets'] ?? 0),
            'total_valuation' => (float)($row['total_valuation'] ?? 0),
            'total_valuation_formatted' => 'Rp ' . number_format((float)($row['total_valuation'] ?? 0), 0, ',', '.'),
            'count_available' => (int)($row['count_available'] ?? 0),
            'count_in_use' => (int)($row['count_in_use'] ?? 0),
            'count_maintenance' => (int)($row['count_maintenance'] ?? 0),
            'count_disposed' => (int)($row['count_disposed'] ?? 0),
        ];
    }

    /**
     * @param int|null $fromLocationId Lokasi asal, null bila tidak diketahui.
     * @param int|null $toLocationId Lokasi tujuan.
     */
    private function recordMovement(
        int $assetId,
        MovementType $type,
        ?int $fromLocationId,
        ?int $toLocationId,
        string $actor,
        ?string $recipient,
        ?string $notes
    ): void {
        (new MovementRepository($this->db))->record([
            'asset_id' => $assetId,
            'movement_type' => $type->value,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'actor_name' => $actor,
            'recipient_name' => $recipient,
            'notes' => $notes,
        ]);
    }
}
