<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kind of a knowledge base entry (guide.guide_type, konzept-3).
 */
enum GuideType: string
{
    case Anleitung = 'anleitung';
    case Rezept = 'rezept';
    case Steckbrief = 'steckbrief';
    case Notfallplan = 'notfallplan';
}
