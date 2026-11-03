<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * State of an aid such as a wheelchair (asset.status, konzept-3).
 */
enum AssetStatus: string
{
    case InBetrieb = 'in_betrieb';
    case Defekt = 'defekt';
    case InReparatur = 'in_reparatur';
    case Ausgemustert = 'ausgemustert';
}
