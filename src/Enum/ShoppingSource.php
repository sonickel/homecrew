<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a list entry comes from (shopping_item.source, konzept-2 §9).
 */
enum ShoppingSource: string
{
    case Manuell = 'manuell';
    case Aufgabe = 'aufgabe';
    case Rezept = 'rezept';
    case Ki = 'ki';
    case Meldung = 'meldung';
}
