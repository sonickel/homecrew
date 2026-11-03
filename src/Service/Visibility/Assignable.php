<?php

declare(strict_types=1);

namespace App\Service\Visibility;

use App\Entity\AppUser;
use App\Enum\AssigneeRole;
use App\Enum\Visibility;

/**
 * Something with responsibility and visibility (konzept-2 §5.2–5.3): tasks now, appointments from M8.
 * Decided by VisibilityRules.
 */
interface Assignable
{
    public function getVisibility(): Visibility;

    /** A certain person – takes precedence over the role. */
    public function getAssigneeUser(): ?AppUser;

    /** Without person and role: "wer gerade da ist". */
    public function getAssigneeRole(): ?AssigneeRole;

    public function getCreatedBy(): ?AppUser;
}
