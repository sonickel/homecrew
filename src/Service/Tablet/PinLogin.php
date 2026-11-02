<?php

declare(strict_types=1);

namespace App\Service\Tablet;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Repository\AppSettingRepository;
use App\Security\Pin\PinLoginException;
use App\Security\Pin\PinRules;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Login by name and PIN at a paired tablet (konzept-1 §5, konzept-12 §2): only persons with PIN login switched on,
 * only at a tablet in mode "Persönlich" or "Beides", in the home network unless "PIN nur im Heimnetz" is off.
 * Three wrong PINs give the person a 5-minute pause; on top, each tablet allows 15 tries in 15 minutes
 * (against trying through all persons). The PIN is hashed like a password.
 */
final readonly class PinLogin
{
    public const int MAX_ATTEMPTS = 3;
    public const int PAUSE_MINUTES = 5;

    public function __construct(
        private DeviceCookie $deviceCookie,
        private HomeNetwork $homeNetwork,
        private AppSettingRepository $settingRepository,
        private PasswordHasherFactoryInterface $hasherFactory,
        private AuditLogger $auditLogger,
        private HouseholdContext $household,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.pin_login')]
        private RateLimiterFactoryInterface $deviceLimiter,
    ) {
    }

    /**
     * The tablet a PIN login is possible at from this request, or null.
     */
    public function deviceFor(Request $request): ?Device
    {
        $device = $this->deviceCookie->pairedDevice($request);
        if (null === $device || !$device->getMode()->offersPinLogin()) {
            return null;
        }

        return $this->homeNetwork->contains($request) || !$this->settingRepository->get()->isPinLanOnly() ? $device : null;
    }

    public function canUse(AppUser $person): bool
    {
        return $person->isPinLoginEnabled() && $person->hasPin() && !$person->hasLeft() && !$person->isBlockedOn($this->household->today());
    }

    /**
     * One try at a time per person: the person's row stays locked from reading the counter until the try is saved,
     * so parallel requests get no more tries than one after the other.
     *
     * @throws PinLoginException with the text the tablet shows
     */
    public function verify(AppUser $person, string $pin, Device $device): void
    {
        // A wrong PIN is no exception in here: wrapInTransaction() would roll back the counted try
        $problem = $this->entityManager->wrapInTransaction(function () use ($person, $pin, $device): ?string {
            $this->entityManager->refresh($person, LockMode::PESSIMISTIC_WRITE);

            return $this->attempt($person, $pin, $device);
        });

        if (null !== $problem) {
            throw new PinLoginException($problem);
        }
    }

    /**
     * @return string|null what the tablet shows when the try did not work
     */
    private function attempt(AppUser $person, string $pin, Device $device): ?string
    {
        if (!$this->canUse($person)) {
            return 'Für dich ist die PIN-Anmeldung nicht eingeschaltet. Ein Admin kann sie einschalten.';
        }

        $now = $this->clock->now();
        $lockedUntil = $person->getPinLockedUntil();
        if (null !== $lockedUntil && $person->isPinLockedAt($now)) {
            return self::waitText('Zu viele falsche Versuche.', (int) ceil(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60));
        }

        $limit = $this->deviceLimiter->create($device->getUserIdentifier())->consume();
        if (!$limit->isAccepted()) {
            // The rate limiter works with the system time, so the waiting time is computed with it as well
            return self::waitText('Zu viele Versuche an diesem Tablet.', (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
        }

        if ($this->hasher()->verify((string) $person->getPinHash(), $pin)) {
            $person->resetPinFailures();

            return null;
        }

        $left = $person->registerFailedPin($now, self::MAX_ATTEMPTS, self::PAUSE_MINUTES);
        $this->auditLogger->log(
            0 === $left ? AuditAction::AnmeldungGesperrt : AuditAction::AnmeldungFehlschlag,
            $person,
            null,
            ['schritt' => 'pin'],
            AuditVia::Tablet,
            $device,
        );

        return 0 === $left
            ? \sprintf('Die PIN stimmt nicht. Bitte warte %d Minuten und versuch es dann noch einmal.', self::PAUSE_MINUTES)
            : \sprintf('Die PIN stimmt nicht. Noch %s, dann %d Minuten Pause.', 1 === $left ? '1 Versuch' : $left.' Versuche', self::PAUSE_MINUTES);
    }

    /**
     * Replaces the PIN (the start PIN at the first PIN login, or later in "Mein Konto").
     *
     * @throws \DomainException with the text the form shows
     */
    public function change(AppUser $person, string $newPin, Device $device): void
    {
        $problem = PinRules::problem($newPin);
        if (null !== $problem) {
            throw new \DomainException($problem);
        }
        if ($this->hasher()->verify((string) $person->getPinHash(), $newPin)) {
            throw new \DomainException('Die neue PIN muss sich von der bisherigen unterscheiden.');
        }

        $person->changePin($this->hasher()->hash($newPin), mustChange: false);
        $this->auditLogger->log(AuditAction::PinAendern, $person, $person, [], AuditVia::Tablet, $device);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->hasherFactory->getPasswordHasher(AppUser::class);
    }

    private static function waitText(string $what, int $minutes): string
    {
        $minutes = max(1, $minutes);

        return 1 === $minutes
            ? $what.' Bitte warte eine Minute und versuch es dann noch einmal.'
            : \sprintf('%s Bitte warte %d Minuten und versuch es dann noch einmal.', $what, $minutes);
    }
}
