<?php

namespace App\Enums;

enum SyncTargetType: string
{
    case User = 'user';
    case Organization = 'organization';
}
