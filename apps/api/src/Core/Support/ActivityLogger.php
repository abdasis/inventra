<?php

declare(strict_types=1);

namespace Inventra\Core\Support;

use Inventra\Core\Auth\Auth;
use Inventra\Core\Repositories\ActivityLogRepository;
use Throwable;

final class ActivityLogger
{
    /**
     * Kegagalan pencatatan audit tidak boleh menggagalkan request utama,
     * jadi exception apa pun di sini hanya dicatat ke error log.
     */
    public static function log(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $ipAddress = null
    ): void {
        try {
            $user = Auth::user();

            (new ActivityLogRepository())->record([
                'user_id' => $user !== null ? (int)$user['id'] : null,
                'user_name' => $user['name'] ?? null,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'description' => $description,
                'ip_address' => $ipAddress,
            ]);
        } catch (Throwable $e) {
            error_log('[activity-log] Gagal mencatat aksi "' . $action . '": ' . $e->getMessage());
        }
    }
}
