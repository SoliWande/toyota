<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dealer = 'dealer';

    // Compatibility for untouched historical reporting. This is NOT a login role
    // and is deliberately absent from cases()/tryFrom().
    public const Sales = LegacyAccountRole::Sales;
}
