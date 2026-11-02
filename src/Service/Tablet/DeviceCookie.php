<?php

declare(strict_types=1);

namespace App\Service\Tablet;

use App\Entity\Device;
use App\Repository\DeviceRepository;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * The device token of a paired tablet: 32 random bytes in an HttpOnly cookie, only its SHA-256 in device.token_hash.
 * Whoever holds the cookie is the tablet – unpairing in "Verwaltung → Geräte" makes it worthless at once.
 */
final readonly class DeviceCookie
{
    public const string NAME = 'hc_device';
    /** Browsers keep cookies at most 400 days; activity renews it (App\EventSubscriber\TabletRequestSubscriber). */
    public const int LIFETIME_DAYS = 400;
    private const string ATTRIBUTE = '_hc_device';

    public function __construct(
        private DeviceRepository $deviceRepository,
        private ClockInterface $clock,
    ) {
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function token(Request $request): ?string
    {
        $token = $request->cookies->all()[self::NAME] ?? null;

        return \is_string($token) && '' !== $token ? $token : null;
    }

    /**
     * The device the cookie belongs to – also an unpaired one (the tablet then says so). Looked up once per request.
     *
     * @throws DbalException when the database is down – the login of the tablet must not take that for "unpaired"
     */
    public function lookUp(Request $request): ?Device
    {
        if (!$request->attributes->has(self::ATTRIBUTE)) {
            $token = $this->token($request);
            $request->attributes->set(self::ATTRIBUTE, null === $token ? null : $this->deviceRepository->findOneByTokenHash(self::hash($token)));
        }

        $device = $request->attributes->get(self::ATTRIBUTE);

        return $device instanceof Device ? $device : null;
    }

    /**
     * Like lookUp(), but without database (error pages, layout) there is none.
     */
    public function device(Request $request): ?Device
    {
        try {
            return $this->lookUp($request);
        } catch (DbalException) {
            return null;
        }
    }

    /**
     * The paired (not unpaired) device of this browser, wherever the request comes from.
     */
    public function pairedDevice(Request $request): ?Device
    {
        $device = $this->device($request);

        return null === $device || $device->isRevoked() ? null : $device;
    }

    public function create(string $token, Request $request): Cookie
    {
        return Cookie::create(self::NAME, $token)
            ->withExpires($this->clock->now()->modify('+'.self::LIFETIME_DAYS.' days'))
            ->withSecure($request->isSecure())
            ->withHttpOnly()
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function clear(Request $request): Cookie
    {
        return Cookie::create(self::NAME, null)
            ->withExpires(1)
            ->withSecure($request->isSecure())
            ->withHttpOnly()
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
