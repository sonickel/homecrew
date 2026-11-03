<?php

declare(strict_types=1);

namespace App\Security\Attribute;

/**
 * Marks a controller (class or action) the team tablet may open without a personal login.
 * Every other page refuses the tablet (App\EventSubscriber\TeamTabletGuard) – so a new page never shows up
 * at the tablet by accident. What the tablet then sees is still decided by the voters.
 * Persons logged in at a tablet (PIN or full login) are not affected.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class TeamTabletAllowed
{
}
