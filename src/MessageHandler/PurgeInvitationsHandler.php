<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeInvitations;
use App\Repository\InvitationRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeInvitationsHandler
{
    public function __construct(
        private InvitationRepository $invitationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeInvitations $message): int
    {
        return $this->invitationRepository->deleteExpired($this->clock->now());
    }
}
