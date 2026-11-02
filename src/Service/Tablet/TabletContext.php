<?php

declare(strict_types=1);

namespace App\Service\Tablet;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\EmergencyContact;
use App\Repository\AppSettingRepository;
use App\Repository\EmergencyContactRepository;
use App\Security\Device\DeviceToken;
use App\Security\Pin\PinLoginToken;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Who uses this browser right now, seen from the tablet's side (konzept-12): the team tablet itself, a person
 * logged in by PIN, or a person with a full login at a paired tablet – and how long such a login lasts without touch.
 */
final readonly class TabletContext
{
    /** A full personal login at a paired tablet (e.g. "Admin-Anmeldung") ends after 5 minutes without touch. */
    public const int FULL_LOGIN_IDLE_SECONDS = 300;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private DeviceCookie $deviceCookie,
        private AppSettingRepository $settingRepository,
        private EmergencyContactRepository $contactRepository,
    ) {
    }

    /**
     * The team tablet when nobody is logged in personally (any mode), otherwise null.
     */
    #[AsTwigFunction('team_device')]
    public function teamDevice(): ?Device
    {
        $token = $this->security->getToken();

        return $token instanceof DeviceToken ? $token->getDevice() : null;
    }

    /**
     * The paired tablet this browser is (by its cookie), whoever is logged in – null on phones and PCs.
     */
    public function pairedDevice(): ?Device
    {
        $request = $this->requestStack->getMainRequest();

        return null === $request ? null : $this->deviceCookie->pairedDevice($request);
    }

    public function isPinLogin(): bool
    {
        return $this->security->getToken() instanceof PinLoginToken;
    }

    /**
     * Seconds without touch after which a personal login at a paired tablet ends and the tablet shows the team view
     * again: the "Auto-Sperre" setting for a PIN login, 5 minutes for a full login. Null when no person is logged in
     * at a tablet (on phones and PCs the 8 hours of App\EventSubscriber\SessionIdleSubscriber apply).
     */
    #[AsTwigFunction('tablet_logout_seconds')]
    public function personIdleSeconds(): ?int
    {
        if (!$this->security->getUser() instanceof AppUser) {
            return null;
        }
        if ($this->isPinLogin()) {
            return $this->settingRepository->get()->getTabletLockSeconds();
        }

        return null === $this->pairedDevice() ? null : self::FULL_LOGIN_IDLE_SECONDS;
    }

    /**
     * Seconds without touch until the screensaver of the team tablet starts; null for everybody else.
     */
    #[AsTwigFunction('screensaver_seconds')]
    public function screensaverSeconds(): ?int
    {
        return $this->teamDevice()?->getDimAfterSeconds();
    }

    /**
     * What the screensaver shows without any login (konzept-3 §2): contacts for everybody, marked for the lock screen.
     *
     * @return list<EmergencyContact> empty for everybody but the team tablet
     */
    #[AsTwigFunction('lockscreen_contacts')]
    public function lockScreenContacts(): array
    {
        return null === $this->teamDevice() ? [] : $this->contactRepository->findForLockScreen();
    }
}
