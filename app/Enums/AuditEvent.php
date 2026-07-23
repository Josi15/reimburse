<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Submit = 'submit';
    case Approve = 'approve';
    case Reject = 'reject';
    case Payment = 'payment';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
