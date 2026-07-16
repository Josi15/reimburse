<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case Manager = 'manager';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Finance => 'Finance',
        };
    }
}
