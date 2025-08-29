<?php

namespace App\Enum;

enum RelationshipToPropertyEnum: string
{
    case DIRECT = 'direct';
    case INTERMEDIARY = 'intermediary';
    case INDIRECT = 'indirect';

    public function label(): string
    {
        return match($this) {
            self::DIRECT => 'Je suis le propriétaire de ce bien',
            self::INTERMEDIARY => 'Je suis un mandataire ou gestionnaire de ce bien',
            self::INDIRECT => 'Je représente un tiers (relation indirecte) de ce bien',
        };
    }
}