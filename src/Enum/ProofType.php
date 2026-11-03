<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Proof when completing a task (task.proof_type) – photo and measured value come with M11 and stage 2.
 */
enum ProofType: string
{
    case Keiner = 'keiner';
    case Foto = 'foto';
    case Kommentar = 'kommentar';
    case Messwert = 'messwert';
}
