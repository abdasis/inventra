<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Staf',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Admin => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::Staff => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
