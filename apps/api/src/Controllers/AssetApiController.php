<?php

declare(strict_types=1);

namespace Inventra\Api\Controllers;

use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Models\AssetModel;
use Inventra\Core\Models\CategoryModel;
use Inventra\Core\Models\LocationModel;
use Inventra\Core\Models\MovementModel;
use Throwable;

final class AssetApiController
{
    private AssetModel $assetModel;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;
    private MovementModel $movementModel;

    public function __construct()
    {
        $this->assetModel = new AssetModel();
        $this->categoryModel = new CategoryModel();
        $this->locationModel = new LocationModel();
        $this->movementModel = new MovementModel();
    }

    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'category_id' => $_GET['category_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'location_id' => $_GET['location_id'] ?? null,
        ];

        $assets = $this->assetModel->all($filters);
        $this->json(['success' => true, 'data' => $assets, 'count' => count($assets)]);
    }

    public function show(int $id): void
    {
        $asset = $this->assetModel->find($id);
        if (!$asset) {
            $this->json(['success' => false, 'message' => 'Aset tidak ditemukan'], 404);
            return;
        }

        $this->json(['success' => true, 'data' => $asset]);
    }

    public function store(): void
    {
        $input = $this->getRequestPayload();
        try {
            $dto = AssetDTO::fromArray($input);
            $newId = $this->assetModel->create($dto);
            $created = $this->assetModel->find($newId);
            $this->json(['success' => true, 'message' => 'Aset berhasil didaftarkan', 'data' => $created], 201);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(int $id): void
    {
        $asset = $this->assetModel->find($id);
        if (!$asset) {
            $this->json(['success' => false, 'message' => 'Aset tidak ditemukan'], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['id'] = $id;

        try {
            $dto = AssetDTO::fromArray($input);
            $this->assetModel->update($id, $dto);
            $updated = $this->assetModel->find($id);
            $this->json(['success' => true, 'message' => 'Data aset berhasil diperbarui', 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): void
    {
        $asset = $this->assetModel->find($id);
        if (!$asset) {
            $this->json(['success' => false, 'message' => 'Aset tidak ditemukan'], 404);
            return;
        }

        $this->assetModel->delete($id);
        $this->json(['success' => true, 'message' => 'Aset berhasil dihapus']);
    }

    public function checkOut(int $id): void
    {
        $payload = $this->getRequestPayload();
        $recipient = trim((string)($payload['recipient_name'] ?? ''));
        $targetLocation = (int)($payload['target_location_id'] ?? 0);
        $actor = trim((string)($payload['actor_name'] ?? 'Admin GA'));
        $notes = !empty($payload['notes']) ? (string)$payload['notes'] : null;

        if ($recipient === '' || $targetLocation <= 0) {
            $this->json(['success' => false, 'message' => 'Nama penerima dan lokasi target wajib diisi'], 422);
            return;
        }

        try {
            $this->assetModel->checkOut($id, $recipient, $targetLocation, $actor, $notes);
            $updated = $this->assetModel->find($id);
            $this->json(['success' => true, 'message' => 'Serah terima / checkout aset berhasil', 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkIn(int $id): void
    {
        $payload = $this->getRequestPayload();
        $returnLocation = (int)($payload['return_location_id'] ?? 0);
        $actor = trim((string)($payload['actor_name'] ?? 'Admin GA'));
        $notes = !empty($payload['notes']) ? (string)$payload['notes'] : null;

        if ($returnLocation <= 0) {
            $this->json(['success' => false, 'message' => 'Lokasi gudang/ruang penyimpanan wajib diisi'], 422);
            return;
        }

        try {
            $this->assetModel->checkIn($id, $returnLocation, $actor, $notes);
            $updated = $this->assetModel->find($id);
            $this->json(['success' => true, 'message' => 'Pengembalian / checkin aset berhasil', 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function stats(): void
    {
        $stats = $this->assetModel->getStats();
        $recentMovements = $this->movementModel->getRecent(5);
        $this->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_movements' => $recentMovements,
            ],
        ]);
    }

    public function categories(): void
    {
        $items = $this->categoryModel->all();
        $this->json(['success' => true, 'data' => $items]);
    }

    public function locations(): void
    {
        $items = $this->locationModel->all();
        $this->json(['success' => true, 'data' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestPayload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
