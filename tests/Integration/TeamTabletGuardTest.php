<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\UserRole;
use App\EventSubscriber\TeamTabletGuard;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Device\DeviceToken;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The team tablet only reaches pages marked with #[TeamTabletAllowed] (allow list, review M4).
 */
final class TeamTabletGuardTest extends KernelTestCase
{
    /**
     * Every route the team tablet may open – a new entry here is a conscious decision.
     */
    private const array ALLOWED_ROUTES = [
        'app_emergency',
        'app_home',
        'app_invitation',
        'app_invitation_password',
        'app_login',
        'app_logout',
        'app_pairing',
        'app_tablet_people',
        'app_tablet_pin',
        'app_task_complete',
        'app_task_edit',
        'app_task_handover',
        'app_task_index',
        'app_task_new',
        'app_task_postpone',
        'app_task_show',
        'app_task_skip',
        'app_task_undo',
        'webauthn.controller.security.main.request.options',
        'webauthn.controller.security.main.request.result',
    ];

    public function testOnlyTheListedRoutesAreOpenForTheTeamTablet(): void
    {
        $allowed = [];
        // Every route counts – controllers of bundles only through the fixed list of the guard
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (\in_array($name, TeamTabletGuard::BUNDLE_ROUTES, true)) {
                $allowed[] = $name;
                continue;
            }
            if (!\is_string($controller) || !str_starts_with($controller, 'App\\')) {
                continue;
            }
            [$class, $method] = explode('::', $controller) + [1 => '__invoke'];
            \assert(class_exists($class));
            $reflection = new \ReflectionClass($class);
            if ([] !== $reflection->getAttributes(TeamTabletAllowed::class) || [] !== $reflection->getMethod($method)->getAttributes(TeamTabletAllowed::class)) {
                $allowed[] = $name;
            }
        }
        sort($allowed);

        self::assertSame(self::ALLOWED_ROUTES, $allowed);
    }

    public function testTheTeamTabletIsRefusedOnAnUnmarkedPage(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->guard(self::teamTablet(), new UnmarkedController());
    }

    public function testTheTeamTabletReachesAMarkedPage(): void
    {
        $this->guard(self::teamTablet(), new MarkedController());
        $this->guard(self::teamTablet(), new MarkedActionController());

        $this->addToAssertionCount(2);
    }

    public function testPersonsAreNotAffected(): void
    {
        $person = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));

        $this->guard(new UsernamePasswordToken($person, 'main', ['ROLE_USER']), new UnmarkedController());

        $this->addToAssertionCount(1);
    }

    public function testSubRequestsAreNotChecked(): void
    {
        $this->guard(self::teamTablet(), new UnmarkedController(), HttpKernelInterface::SUB_REQUEST);

        $this->addToAssertionCount(1);
    }

    private function guard(TokenInterface $token, object $controller, int $requestType = HttpKernelInterface::MAIN_REQUEST): void
    {
        static::getContainer()->get(TokenStorageInterface::class)->setToken($token);
        $callable = [$controller, 'show'];
        \assert(\is_callable($callable));
        $event = new ControllerArgumentsEvent(static::$kernel ?? self::bootKernel(), $callable, [], new Request(), $requestType);

        static::getContainer()->get(TeamTabletGuard::class)->onKernelControllerArguments($event);
    }

    private static function teamTablet(): DeviceToken
    {
        $device = new Device('Flur', hash('sha256', 'token'));

        return new DeviceToken($device, 'main', $device->getRoles());
    }
}

final class UnmarkedController
{
    public function show(): Response
    {
        return new Response();
    }
}

#[TeamTabletAllowed]
final class MarkedController
{
    public function show(): Response
    {
        return new Response();
    }
}

final class MarkedActionController
{
    #[TeamTabletAllowed]
    public function show(): Response
    {
        return new Response();
    }
}
