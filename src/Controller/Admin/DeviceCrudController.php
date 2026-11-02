<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Enum\FontSize;
use App\Security\Passkey\PasskeySupport;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Tablet\DevicePairing;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Geräte (konzept-11 §2): pair a tablet by QR code, name, mode, screensaver, font size, last seen, unpair.
 * Devices are never deleted – they stay as anchor for the log and later for completions and reports.
 *
 * @extends AbstractCrudController<Device>
 */
#[AdminRoute(path: '/geraete', name: 'device')]
#[IsGranted(Permission::ADMIN)]
final class DeviceCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';
    private const array DIM_MINUTES = ['1 Minute' => 1, '2 Minuten' => 2, '5 Minuten' => 5, '10 Minuten' => 10, '15 Minuten' => 15, '30 Minuten' => 30];

    public function __construct(
        private readonly DevicePairing $pairing,
        private readonly HouseholdContext $household,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Device::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tablet')
            ->setEntityLabelInPlural('Tablets')
            ->setPageTitle(Crud::PAGE_INDEX, 'Geräte')
            ->setPageTitle(Crud::PAGE_EDIT, 'Tablet bearbeiten')
            ->setHelp(Crud::PAGE_INDEX, 'Gekoppelte Tablets zeigen im WLAN zu Hause ohne Anmeldung die Team-Ansicht oder die Namen zum Anmelden mit PIN. Ein verlorenes Tablet entkoppelst du – es ist dann sofort wirkungslos.')
            ->setDefaultSort(['revokedAt' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        $pair = Action::new('pair', 'Tablet koppeln', 'fa fa-qrcode')
            ->linkToRoute('admin_device_pair')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-primary');
        $unpair = Action::new('unpair', 'Entkoppeln', 'fa fa-link-slash')
            ->linkToRoute('admin_device_unpair', static fn (Device $device): array => ['entityId' => $device->getId()])
            ->displayIf(static fn (Device $device): bool => !$device->isRevoked());

        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $pair)
            ->add(Crud::PAGE_INDEX, $unpair)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action
                ->setLabel('Bearbeiten')
                ->displayIf(static fn (Device $device): bool => !$device->isRevoked()))
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Speichern'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(NullFilter::new('revokedAt', 'Entkoppelt')->setChoiceLabels('nein', 'ja'));
    }

    /**
     * EasyAdmin prints formatted values as HTML (|raw), so every formatter escapes its text.
     */
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Name')
            ->setHelp('Wo das Tablet steht, z. B. „Flur“ oder „Küche“.');
        yield $this->textColumn('modeLabel', 'Modus', static fn (Device $device): string => $device->getMode()->label());
        yield Field::new('mode', 'Modus')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => DeviceMode::class,
                'expanded' => true,
                'choice_label' => static fn (DeviceMode $mode): string => $mode->description(),
            ])
            ->setHelp('Was das Tablet ohne Anmeldung zeigt. Wechseln geht jederzeit, es geht nichts verloren.');
        // Generic fields lose formatValue() on the index (EasyAdmin guesses a type), so the list has text columns
        yield $this->textColumn('dimLabel', 'Bildschirmschoner', static fn (Device $device): string => 1 === $device->getDimAfterMinutes() ? '1 Minute' : \sprintf('%d Minuten', $device->getDimAfterMinutes()));
        yield Field::new('dimAfterMinutes', 'Bildschirmschoner nach')
            ->onlyOnForms()
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions(['choices' => self::DIM_MINUTES])
            ->setHelp('Ohne Bedienung zeigt das Tablet nur noch Uhrzeit, Notruf und die Notfallkontakte für den Sperrbildschirm.');
        yield $this->textColumn('fontLabel', 'Schriftgröße', static fn (Device $device): string => $device->getFontSize()->label());
        yield Field::new('fontSize', 'Schriftgröße')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => FontSize::class,
                'choice_label' => static fn (FontSize $size): string => $size->label(),
            ])
            ->setHelp('Gilt für die Team-Ansicht. Wer sich anmeldet, sieht die eigene Schriftgröße.');
        yield $this->textColumn('lastSeen', 'Zuletzt aktiv', fn (Device $device): string => $device->getLastSeenAt()?->setTimezone($this->household->timezone())->format('d.m.Y, H:i') ?? 'noch nie');
        yield $this->textColumn('status', 'Status', static fn (Device $device): string => $device->isRevoked() ? 'entkoppelt' : 'gekoppelt');
    }

    /**
     * Step 1: the admin names the tablet. Step 2 (POST): a pairing code as QR code, valid 10 minutes.
     */
    #[AdminRoute(path: '/koppeln', name: 'pair', options: ['methods' => ['GET', 'POST']])]
    public function pair(Request $request, #[CurrentUser] AppUser $admin, PasskeySupport $passkeySupport): Response
    {
        $name = '';
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('device-pair', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $name = trim($request->getPayload()->getString('name'));
            $error = match (true) {
                '' === $name => 'Bitte gib dem Tablet einen Namen, z. B. „Flur“ oder „Küche“.',
                mb_strlen($name) > 50 => 'Der Name darf höchstens 50 Zeichen haben.',
                default => null,
            };
            if (null === $error) {
                ['code' => $code, 'expires_at' => $expiresAt] = $this->pairing->createCode($name, $admin);
                $url = $passkeySupport->linkBase($request).$this->generateUrl('app_pairing').'#'.$code;
                $qrCode = (new Builder(
                    writer: new SvgWriter(),
                    data: $url,
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: 280,
                    margin: 8,
                ))->build();

                $response = $this->render('admin/device/pairing_code.html.twig', [
                    'name' => $name,
                    'code' => implode(' ', str_split($code, 4)),
                    'qr' => $qrCode->getDataUri(),
                    'address' => $passkeySupport->linkBase($request).$this->generateUrl('app_pairing'),
                    'expires_at' => $expiresAt,
                ]);
                $response->headers->set('Cache-Control', 'no-store');

                return $response;
            }
        }

        return $this->render('admin/device/pair.html.twig', [
            'name' => $name,
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Unpairing asks once (GET), then acts (POST with CSRF token).
     */
    #[AdminRoute(path: '/{entityId}/entkoppeln', name: 'unpair', options: ['methods' => ['GET', 'POST'], 'requirements' => ['entityId' => '\d+']])]
    public function unpair(#[MapEntity(id: 'entityId')] Device $device, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('device-unpair-'.$device->getId(), $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $this->pairing->unpair($device, $admin);
            // EasyAdmin prints flash messages as HTML (|raw); the name was typed by an admin
            $this->addFlash('success', \sprintf('Das Tablet „%s“ ist entkoppelt und zeigt ab sofort nichts mehr an.', htmlspecialchars($device->getName(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')));

            return $this->redirectToRoute('admin_device_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/device/unpair.html.twig', [
            'device' => $device,
        ]);
    }

    /**
     * @param \Closure(Device): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->onlyOnIndex()
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, Device $device): string => htmlspecialchars($text($device), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
