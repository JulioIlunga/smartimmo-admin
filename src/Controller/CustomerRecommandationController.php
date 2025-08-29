<?php

namespace App\Controller;

use App\Entity\Preference;
use App\Repository\CountryRepository;
use App\Repository\PreferenceRepository;
use App\Repository\UserRepository;
use App\Service\LeadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Commune;
use App\Entity\Country;
use App\Entity\PaymentForPreference;
use App\Entity\Property;
use App\Entity\Province;
use App\Repository\AdminConfigurationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function Symfony\Component\Clock\now;

class CustomerRecommandationController extends AbstractController
{
    private LoggerInterface $logger;


    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    #[Route('/customer/recommandation', name: 'app_customer_recommandation')]
    public function index(PreferenceRepository $preferenceRepository, UserRepository $userRepository,AdminConfigurationRepository $adminConfigurationRepository): Response
    {
        $this->logger->info('Authenticator initialized');

        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $preferences = $preferenceRepository->findBy(['user' => $user, 'deleted' => false], ['createdAt' => 'DESC']);
        $amount = $adminConfigurationRepository->findOneBy(['id' => 1])->getPreferencePrice();

        return $this->render('customer_recommandation/index.html.twig', [
            'preferences' => $preferences,
            'amount' => $amount,
        ]);
    }

    #[Route('/customer/recommandation/new', name: 'app_customer_recommandation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CountryRepository $countryRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager, LeadService $leadService
    ): Response {
        $countries = $countryRepository->findAll();

        // getUser() already returns the logged-in User (or null if not authenticated)
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour enregistrer une préférence.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request;

            // --- Retrieve location entities (IDs come from selects) ---
            $provinceEntity = null;
            $communeEntity  = null;

            if ($data->get('province')) {
                $provinceEntity = $entityManager->getRepository(Province::class)->find($data->get('province'));
            }
            if ($data->get('commune')) {
                $communeEntity = $entityManager->getRepository(Commune::class)->find($data->get('commune'));
            }

            // Basic validation: location presence
            if (!$provinceEntity || !$communeEntity) {
                $this->addFlash('error', 'Veuillez choisir une ville et une commune valides.');
                return $this->render('customer_recommandation/_new.html.twig', [
                    'countries' => $countries,
                ]);
            }

            // --- Parse numbers safely ---
            $minPrice  = (float)($data->get('budget_min') ?? 0);
            $maxPrice  = (float)($data->get('budget_max') ?? 0);

            if ($maxPrice > 0 && $minPrice > 0 && $maxPrice < $minPrice) {
                $this->addFlash('error', 'Le budget maximum doit être supérieur ou égal au budget minimum.');
                return $this->render('customer_recommandation/_new.html.twig', [
                    'countries' => $countries,
                ]);
            }

            $bedrooms  = (int)($data->get('bedrooms') ?? 0);
            $bathrooms = (int)($data->get('bathrooms') ?? 0);

            // --- Dates (optional) ---
            $moveInEarliest = $data->get('move_in_earliest') ? \DateTimeImmutable::createFromFormat('Y-m-d', $data->get('move_in_earliest')) : null;
            $moveInLatest   = $data->get('move_in_latest')   ? \DateTimeImmutable::createFromFormat('Y-m-d', $data->get('move_in_latest'))   : null;

            if ($moveInEarliest && $moveInLatest && $moveInLatest < $moveInEarliest) {
                $this->addFlash('error', 'La date « Au plus tard » doit être postérieure ou égale à la date « Au plus tôt ».');
                return $this->render('customer_recommandation/_new.html.twig', [
                    'countries' => $countries,
                ]);
            }

            // --- Other new fields (strings/ints) ---
            $transaction     = (string)$data->get('transaction');       // rent | buy
            $propertyType    = $data->get('property_type') ?: null;


            // --- Build Preference ---
            $preference = new Preference();

            // If your entity stores text names for location (as your original code suggests):
            $preference->setCity($provinceEntity);
            $preference->setProvince($provinceEntity->getName());
            $preference->setCommune($communeEntity->getName());

            // Always present (existing in your controller)
            $preference
                ->setCode('lead-'.uniqid())
                ->setUser($user)
                ->setMaxPrice($maxPrice)
                ->setMinPrice($minPrice)
                ->setBedrooms($bedrooms)
                ->setBathrooms($bathrooms)
                ->setTransactionType($transaction) // rent/sell
                ->setStatus(true)
                ->setDeleted(false)
                ->setPaid(false)
                ->setPropertyType($propertyType)
                ->setLeadCost($transaction === 'buy' ? 5 : $leadService->getCreditCost((float) $minPrice));


            // --- Set NEW optional fields only if the setters exist ---

            $parseDate = static function (?string $v): ?\DateTimeImmutable {
                $v = is_string($v) ? trim($v) : null;
                if (!$v) return null;
                // Accepts 'Y-m-d' from <input type="date">; extend if you support other formats
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
                return $dt instanceof \DateTimeImmutable ? $dt : null;
            };

            $csvToArray = static function (?string $v): ?array {
                $v = is_string($v) ? trim($v) : '';
                if ($v === '') return null;
                $parts = array_map('trim', explode(',', $v));
                // strip empties and duplicates
                $parts = array_values(array_unique(array_filter($parts, static fn($x) => $x !== '')));
                return $parts ?: null;
            };

            $enumOrNull = static function (?string $val, array $allowed): ?string {
                $val = is_string($val) ? trim($val) : null;
                return ($val && in_array($val, $allowed, true)) ? $val : null;
            };

            $mustHavesRaw     = $request->request->get('must_haves');                 // string CSV
            $moveInEarliestS  = $request->request->get('move_in_earliest');           // 'Y-m-d'
            $moveInLatestS    = $request->request->get('move_in_latest');             // 'Y-m-d'
            $urgencyRaw       = $request->request->get('urgency');                    // high|medium|low
            $leadDurationRaw  = $request->request->get('lead_duration');              // 30|14|7|until_cancel
            $alertFrequency   = $request->request->get('alert_frequency');            // instant|daily|weekly
            $contactChannel   = $request->request->get('contact_channel');            // whatsapp|phone|email
            $contactTime      = $request->request->get('contact_time');               // morning|afternoon|evening
            $whatsConsentRaw  = $request->request->get('whatsapp_consent');           // '1' or null
            $leadTimeframe    = $request->request->get('lead_timeframe');             // urgent|by_date
            $leadUntilS       = $request->request->get('lead_until');                 // 'Y-m-d' (optional)

            // Cast to correct data types
            $mustHaves        = $csvToArray($mustHavesRaw);                            // ?array
            $moveInEarliest   = $parseDate($moveInEarliestS);                          // ?DateTimeImmutable
            $moveInLatest     = $parseDate($moveInLatestS);                            // ?DateTimeImmutable
            $urgency          = $enumOrNull($urgencyRaw, ['high','medium','low']);     // ?string

            // alertDuration: keep int for numeric, or literal 'until_cancel'
            if ($leadDurationRaw === 'until_cancel') {
                $alertDuration = 'until_cancel';                                       // string
            } elseif (is_numeric($leadDurationRaw)) {
                $alertDuration = (int)$leadDurationRaw;                                // int
            } else {
                $alertDuration = null;                                                 // null
            }

            $alertFrequency   = $enumOrNull($alertFrequency, ['instant','daily','weekly']); // ?string
            $contactChannel   = $enumOrNull($contactChannel, ['whatsapp','phone','email']); // ?string
            $contactTime      = $enumOrNull($contactTime, ['morning','afternoon','evening']); // ?string
            $whatsConsent     = (bool)$whatsConsentRaw;                       // bool

            $leadTimeframe    = $enumOrNull($leadTimeframe, ['urgent','by_date']);     // ?string
            $leadUntil        = ($leadTimeframe === 'by_date') ? $parseDate($leadUntilS) : null; // ?DateTimeImmutable

            // ---- Apply to entity (only if setter exists, as you had) ----
            if (method_exists($preference, 'setMustHaves')) {
                $preference->setMustHaves($mustHaves);                                  // ?array
            }
            if (method_exists($preference, 'setMoveInEarliest')) {
                $preference->setMoveInEarliest($moveInEarliest);                        // ?DateTimeInterface
            }
            if (method_exists($preference, 'setMoveInLatest')) {
                $preference->setMoveInLatest($moveInLatest);                            // ?DateTimeInterface
            }
            if (method_exists($preference, 'setUrgency')) {
                $preference->setUrgency($urgency);                                      // ?string
            }
            if (method_exists($preference, 'setAlertDuration')) {
                // int (days) or 'until_cancel' as string, or null
                $preference->setAlertDuration($alertDuration);                          // int|string|null
            }
            if (method_exists($preference, 'setAlertFrequency')) {
                $preference->setAlertFrequency($alertFrequency);                        // ?string
            }
            if (method_exists($preference, 'setContactChannel')) {
                $preference->setContactChannel($contactChannel);                        // ?string
            }
            if (method_exists($preference, 'setContactTime')) {
                $preference->setContactTime($contactTime);                              // ?string
            }
            if (method_exists($preference, 'setWhatsappConsent')) {
                $preference->setWhatsappConsent($whatsConsent);                         // bool
            }
            // Optional: store the timeframe & date if your entity supports it
            if (method_exists($preference, 'setLeadTimeframe')) {
                $preference->setLeadTimeframe($leadTimeframe);                          // ?string
            }
            if (method_exists($preference, 'setLeadUntil')) {
                $preference->setLeadUntil($leadUntil);                                  // ?DateTimeInterface
            }


            $entityManager->persist($preference);
            $entityManager->flush();

            $this->addFlash('success', 'Préférence enregistrée avec succès.');
            // PRG pattern to avoid resubmission
            return $this->redirectToRoute('app_customer_recommandation_new');
        }

        return $this->render('customer_recommandation/_new.html.twig', [
            'countries' => $countries,
        ]);
    }

    #[Route('/customer/recommandation/remove/{id}', name: 'app_customer_recommandation_remove', methods: ['DELETE'])]
    public function remove(Preference $preference, EntityManagerInterface $entityManager): Response
    {

        $preference->setStatus(false);
        $preference->setDeleted(true);
        $preference->setDeletedAt(new \DateTime());
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Préférence supprimée avec succès.'
        ]);
    }

    #[Route('/customer/recommandation/login/before/pursue', name: 'app_customer_recommandation_login_before_pursue')]
    public function savePage(SessionInterface $session): RedirectResponse
    {
        $session->set('customer_recommandation', true);
        return $this->redirectToRoute('app_login');
    }
}
