<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Actions\Asset\Concerns\BuildsAssetFromPayload;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Mendaftarkan aset baru, satuan maupun massal. Setiap unit mendapat identitas
 * (kode) sendiri dan selalu lahir sebagai aset tersedia tanpa pemegang:
 * penempatan dan peminjaman adalah transaksi terpisah.
 */
final class CreateAssetAction
{
    use BuildsAssetFromPayload;

    /** Batas percobaan membangkitkan kode otomatis sebelum menyerah. */
    private const CODE_ATTEMPTS = 100;

    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ids: list<int>, code: string, quantity: int} ids berurutan sesuai kode.
     */
    public function execute(array $payload): array
    {
        $input = $this->assetFromPayload($this->assets, $payload, null, true);
        $quantity = (int)$input['quantity'];

        $codes = $input['asset_code'] !== ''
            ? [$input['asset_code']]
            : $this->generateCodes($input['item_id'], $quantity);

        $ids = [];
        foreach ($codes as $code) {
            $input['asset_code'] = $code;
            $ids[] = $this->assets->create(AssetDTO::fromArray($input));
        }

        return ['ids' => $ids, 'code' => $codes[0], 'quantity' => count($ids)];
    }

    /**
     * Kode aset berformat {prefix}-{tahun}-{4 digit urut}, dengan prefix kode
     * jenis barang bila aset ditautkan ke jenis barang, selain itu "AST".
     *
     * @return list<string>
     */
    private function generateCodes(?int $itemId, int $quantity): array
    {
        $prefix = 'AST';
        if ($itemId !== null) {
            $item = $this->assets->item($itemId);
            $prefix = $item !== null ? (string)$item['code'] : $prefix;
        }

        $base = $prefix . '-' . date('Y') . '-';
        $sequence = $this->assets->countByCodePrefix($base) + 1;
        $codes = [];
        $attempts = 0;

        while (count($codes) < $quantity) {
            if (++$attempts > self::CODE_ATTEMPTS) {
                throw new ValidationException('Kode aset otomatis gagal dibangkitkan. Isi kode aset secara manual.');
            }

            $code = sprintf('%s%04d', $base, $sequence);
            $sequence++;

            if ($this->assets->codeExists($code)) {
                continue;
            }

            $codes[] = $code;
        }

        return $codes;
    }
}
