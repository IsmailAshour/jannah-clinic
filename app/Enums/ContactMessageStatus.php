<?php

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Archived = 'archived';

    public function labelAr(): string
    {
        return match ($this) {
            self::New => 'جديدة',
            self::Read => 'مقروءة',
            self::Replied => 'تم الرد',
            self::Archived => 'مؤرشفة',
        };
    }
}
