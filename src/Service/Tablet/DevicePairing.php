<?php

declare(strict_types=1);

namespace App\Service\Tablet;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Service\Account\AccessCodeGenerator;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Pairing a tablet (konzept-11 §2 "Geräte"): an admin asks for a pairing code (QR code or typed), the tablet redeems it
 * once within 10 minutes and gets its device token. The code only lives as hash in the cache – no table needed –
 * and travels after "#" in the QR code, so it ends up in no server log.
 */
final readonly class DevicePairing
{
    public const int CODE_MINUTES = 10;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private AccessCodeGenerator $codeGenerator,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private HouseholdContext $household,
        private ClockInterface $clock,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * @return array{code: string, expires_at: \DateTimeImmutable} the plain code is shown once to the admin
     */
    public function createCode(string $deviceName, AppUser $admin): array
    {
        $code = $this->codeGenerator->pairingCode();
        $expiresAt = $this->clock->now()->modify(\sprintf('+%d minutes', self::CODE_MINUTES));

        $item = $this->cache->getItem(self::key($code));
        $item->set(['name' => $deviceName, 'admin' => $admin->getId(), 'expires' => $expiresAt->getTimestamp()]);
        // The cache counts in system time; the validity itself is checked with the clock in pair()
        $item->expiresAfter(self::CODE_MINUTES * 60);
        $this->cache->save($item);

        $this->auditLogger->log(AuditAction::GeraetKopplungscode, $admin, null, [
            'name' => $deviceName,
            'gueltig_bis' => $expiresAt->setTimezone($this->household->timezone())->format('d.m.Y H:i'),
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Redeems a code once and pairs the device.
     *
     * @return array{device: Device, token: string}|null null for an unknown, used or expired code
     */
    public function pair(string $code): ?array
    {
        $code = AccessCodeGenerator::normalizeCode($code);
        if (AccessCodeGenerator::PAIRING_LENGTH !== \strlen($code)) {
            return null;
        }

        $key = self::key($code);
        // Two tablets redeeming the same code at once must not both get paired. One lock for all codes
        // (one household, pairing is rare) – the flock store leaves one file per key behind
        $lock = $this->lockFactory->createLock('device-pairing');
        $lock->acquire(true);
        try {
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return null;
            }
            $this->cache->deleteItem($key);
        } finally {
            $lock->release();
        }

        $data = $item->get();
        if (!\is_array($data) || !\is_string($data['name'] ?? null) || !\is_int($data['expires'] ?? null)
            || $data['expires'] <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        $admin = \is_int($data['admin'] ?? null) ? $this->entityManager->find(AppUser::class, $data['admin']) : null;
        $token = DeviceCookie::newToken();
        $device = new Device($data['name'], DeviceCookie::hash($token), $admin);
        $this->entityManager->persist($device);
        $this->entityManager->wrapInTransaction(function () use ($device): void {
            $this->entityManager->flush();
            $this->auditLogger->log(AuditAction::GeraetKoppeln, null, $device, [], AuditVia::Tablet, $device);
        });

        return ['device' => $device, 'token' => $token];
    }

    /**
     * Unpairing makes the token worthless at once (team view and PIN logins at this tablet end with the next tap).
     */
    public function unpair(Device $device, AppUser $admin): void
    {
        if ($device->isRevoked()) {
            return;
        }

        $device->revoke($this->clock->now());
        $this->auditLogger->log(AuditAction::GeraetEntkoppeln, $admin, $device);
    }

    private static function key(string $code): string
    {
        return 'pairing.'.hash('sha256', $code);
    }
}
