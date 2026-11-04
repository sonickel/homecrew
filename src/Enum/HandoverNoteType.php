<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kind of a handover note (handover_note.note_type, konzept-2 §7) – the handover book follows in M10.
 */
enum HandoverNoteType: string
{
    case Info = 'info';
    case Wichtig = 'wichtig';
    case Frage = 'frage';
}
