<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Success = 'success';
    case Failed = 'failed';
}
