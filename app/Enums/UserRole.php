<?php

namespace App\Enums;

enum UserRole: string
{
    case DONOR = 'donor';
    case HOSPITAL = 'hospital';
    case ADMIN = 'admin';
}
