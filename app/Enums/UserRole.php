<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dealer = 'dealer';
    case Sales = 'sales';
}
