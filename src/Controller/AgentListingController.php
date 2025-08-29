<?php


namespace App\Controller;

use App\Entity\Images;
use App\Entity\Property;
use App\Enum\RelationshipToPropertyEnum;
use App\Form\ImagesType;
use App\Repository\CommuneRepository;
use App\Repository\CountryRepository;
use App\Repository\ImagesRepository;
use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\ProvinceRepository;
use App\Repository\ServiceSupRepository;
use App\Repository\UserRepository;
use App\Service\CSRFProtectionService;
use App\Service\ImageUploaderService;
use App\Service\PropertyService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class AgentListingController extends AbstractController
{
    public function __construct(private ImageUploaderService $imageUploaderService)
    {
    }

    #[Route('/agent/listing', name: 'app_agent_listing')]
    public function index(RequestStack $requestStack): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('agent_work_space/listing/new_listing.html.twig');
    }

    #[Route('/agent/listing/details/{uuid}', name: 'app_agent_listing_listing_detail')]
    public function listingDetail(string $uuid, PropertyRepository $propertyRepository): Response
    {
        // Your app uses uuidProperty in links everywhere
        $property = $propertyRepository->findOneBy(['uuidProperty' => $uuid]);
        if (!$property) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('agent_work_space/listing/new_listing.html.twig', [
            'property' => $property,
        ]);
    }

    /**
     * Create or open a listing (by code when provided).
     */
    #[Route('/agent/create/new/listing/{code?}', name: 'app_agent_listing_new_listing')]
    public function newListing(?string $code, PropertyRepository $propertyRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($code) {
            $property = $propertyRepository->findOneBy(['code' => $code]);
            if (!$property) {
                throw $this->createNotFoundException('Annonce introuvable.');
            }
        } else {
            // New local instance for the first render (not persisted yet)
            $property = new Property();
            // Keep your wizard defaults
            $property->setRegistrationstep(1);
            $this->extracted($property);

            // NOTE: Do NOT set ID manually. Twig can still read null safely if you handle it there.
            // If your Twig absolutely needs an identifier for name suffixes, consider using a temporary token variable instead of setId().
        }

        return $this->render('agent_work_space/listing/new_listing.html.twig', [
            'user' => $userRepository->find($user->getId()),
            'property' => $property,
            'step' => $property->getRegistrationstep(),
        ]);
    }

    #[Route('/agent/listing/property/editing/{code}', name: 'app_agent_listing_property_editing')]
    public function propertyEditing(string $code, ManagerRegistry $doctrine, PropertyRepository $propertyRepository): Response
    {
        $em = $doctrine->getManager();
        $property = $propertyRepository->findOneBy(['code' => $code]);
        if (!$property) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $property->setRegistrationstep(1);
        $em->flush();

        return $this->redirectToRoute('app_agent_listing_edit_listing', [
            'code' => $property->getCode(),

        ]);
    }

    /**
     * Edit wizard view + image upload handling.
     */
    #[Route('/agent/listing/for/edit/{code}', name: 'app_agent_listing_edit_listing')]
    public function editListing(
        string               $code,
        ManagerRegistry      $doctrine,
        Request              $request,
        CountryRepository    $countryRepository,
        UserRepository       $userRepository,
        ServiceSupRepository $serviceSupRepository,
        PropertyRepository   $propertyRepository
    ): Response
    {
        $em = $doctrine->getManager();

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->findOneBy(['code' => $code]);
        if (!$property) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        // Image upload form
        $image = new Images();
        $form = $this->createForm(ImagesType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['name']->getData();

            if ($file) {
                $imageUrl = $this->imageUploaderService->uploadAndResizeImageToS3($file);
                $image->setImageUrl($imageUrl);
                $image->setProperty($property);
                $image->setName(uniqid());
                $image->setStatus(true);

                $em->persist($image);
                $em->flush();

                $this->addFlash('success', "L'image a été enregistrée avec succès.");
            }

            return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
        }

        // Set first image as cover if none yet & at least one exists
        if ($property->getImage() === null && $property->getImages()->count() > 0) {
            $first = $property->getImages()->first();
            if ($first instanceof Images) {
                $property->setImage($first->getImageUrl());
                $em->flush();
            }
        }

        $countries = $countryRepository->findAll();
        $services = $serviceSupRepository->findBy(['user' => $user->getId()]);

        $serviceInProperty = $serviceSupRepository->getServiceSupFromPropertyId($property->getId());
        $serviceInPropertyIds = array_map(static fn($s) => $s->getId(), $serviceInProperty);

        return $this->render('agent_work_space/listing/edit_listing.html.twig', [
            'user' => $userRepository->find($user->getId()),
            'property' => $property,
            'countries' => $countries,
            'services' => $services,
            'serviceInProperty' => $serviceInProperty,
            'formImage' => $form->createView(),
            'step' => $property->getRegistrationstep(),
            'serviceInPropertyIds' => $serviceInPropertyIds,
        ]);
    }

    #[Route('/agent/listing/process/{id}', name: 'app_agent_listing_new_listing_save', methods: ['POST'])]
    public function studentAdmissionSaveForm(
        string             $id,
        Request            $request,
        ManagerRegistry    $doctrine,
        UserRepository     $userRepository,
        PropertyRepository $propertyRepository,
        PropertyService    $ps
    ): Response
    {
        $em = $doctrine->getManager();

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $step = (int)$request->request->get('step', 1);

        // Find existing or start a new one
        $property = $propertyRepository->findOneBy(['id' => $id]);
        if (!$property) {
            $property = new Property();
            $property->setUser($userRepository->find($user->getId()));
            $property->setPublish(false);
            $this->extracted($property);

            $uuid = Uuid::v1();
            $property->setUuid($uuid);
            $property->setUuidProperty($uuid); // if your field is string, this is fine
            $property->setCode(uniqid());
        }

        // Map fields for the current step via your service
        $property = $ps->getPropertyFromForm($request, $id, $property, $step);

        // Advance step
        $nextStep = match ($step) {
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 5,
            5 => 6,
            default => $property->getRegistrationstep(),
        };
        $property->setRegistrationstep($nextStep);

        $em->persist($property);
        $em->flush();

        return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
    }

    #[Route('/agent/listing/back/button/property/registration/{code}/{step}', name: 'app_agent_listing_back_button_admission')]
    public function backButtonAdmission(string $code, int $step, PropertyRepository $propertyRepository, ManagerRegistry $doctrine): Response
    {
        $em = $doctrine->getManager();
        $property = $propertyRepository->findOneBy(['code' => $code]);
        if (!$property) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $prev = match ($step) {
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            default => 1,
        };
        $property->setRegistrationstep($prev);
        $em->flush();

        return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
    }

    #[Route('/agent/listing/save-draft/{id}', name: 'app_agent_listing_save_draft', methods: ['POST'])]
    public function saveDraft(
        string $id,
        Request $request,
        ManagerRegistry $doctrine,
        UserRepository $userRepository,
        PropertyRepository $propertyRepository,
        PropertyService $ps
    ): RedirectResponse
    {
        $em   = $doctrine->getManager();
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié.'], 401);
        }

        // Current step (defaults to 1)
        $step = (int) ($request->request->get('step') ?? 1);

        // Load or create property
        $property = $propertyRepository->findOneBy(['id' => $id]);
        if (!$property) {
            // New draft shell
            $property = new Property();
            $property->setUser($user);
            $property->setPublish(false);
            $this->extracted($property); // your defaults (furniture, wifi, etc.)

            // IDs & codes (mirror your creation logic)
            $uuid = \Symfony\Component\Uid\Uuid::v1();
            $property->setUuid($uuid);
            $property->setUuidProperty($uuid);
            $property->setCode(uniqid());

            // Start the wizard at the current step (don’t advance)
            $property->setRegistrationstep($step);

            $em->persist($property);
            // do not flush yet; we can map fields first and then flush once
        } else {
            // Ownership guard
            if ($property->getUser()?->getId() !== $user->getId()) {
                $this->addFlash('danger', 'Une erreur s\'est produit lors de l\'enregistrement. Veuillez réessayer');
            }
        }

        // Map fields from the posted form WITHOUT advancing registration step
        // IMPORTANT: keep using the original $id because your form field names are built with it (e.g. header-{$id})
        $ps->getPropertyFromForm($request, $id, $property, $step);

        // Keep the highest reached step, but don’t auto-advance
        $current = (int) ($property->getRegistrationstep() ?? 1);
        if ($step > $current) {
            $property->setRegistrationstep($step);
        }

        $em->flush();

        return $this->redirectToRoute('app_agent_work_space_listing');
    }


    #[Route('/agent/listing/work/space/publish/listing/{id}', name: 'app_agent_work_space_publish_listing', methods: ['GET', 'POST'])]
    public function publishProperty(ManagerRegistry $doctrine, CSRFProtectionService $csrf, int $id, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->find($id);
        if (!$property) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('agent_work_space/publishing/publish_form.html.twig', [
            'property' => $property,
            'token' => $csrf->getCSRFTokenForForm(),
        ]);
    }

    #[Route('/agent/listing/work/space/publish/listing/for/save/{id}', name: 'app_agent_work_space_publish_listing_save', methods: ['POST'])]
    public function savePublishing(
        Property                 $property,
        ManagerRegistry          $doctrine,
        CSRFProtectionService    $csrf,
        Request                  $request,
        PropertyStatusRepository $propertyStatusRepository
    ): Response
    {
        $error = false;

        if ($csrf->validateCSRFToken($request)) {
            if ($property->getImages()->count() > 3) {
                if ($property->getUser()->getAgentPhone() === null) {
                    $error = true;
                    $this->addFlash('warning', "Veuillez configurer votre numéro agent.");
                } else {
                    $status = $propertyStatusRepository->findOneBy(['id' => 1]);

                    $relationValue = $request->request->get('relationshipToProperty-' . $property->getId());
                    if ($relationValue) {
                        $property->setRelationshipToProperty(RelationshipToPropertyEnum::from($relationValue));
                    }

                    $property->setPublish(true);
                    $property->setPublishAt(new \DateTime());
                    $property->setPropertyStatus($status);
                    $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));

                    $em = $doctrine->getManager();
                    $em->flush();

                    $this->addFlash('success', "L'annonce Id: " . $property->getUuidProperty() . " a été publiée avec succès.");
                }
            } else {
                $error = true;
                $this->addFlash('warning', "Veuillez finaliser les photos de l'annonce (minimum 4).");
            }
        }

        return $this->render('feedback.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/agent/listing/image/delete/action/for/single/one/{id}', name: 'app_agent_listing_image_action')]
    public function deleteImage(Images $image, ManagerRegistry $doctrine): RedirectResponse
    {
        $em = $doctrine->getManager();
        $property = $image->getProperty();

        $em->remove($image);
        $em->flush();

        $this->addFlash('success', "La photo a été retirée avec succès.");

        return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
    }

    #[Route('/agent/listing/change/status/of/a/property/{id}/{propertyId}', name: 'app_agent_listing_change_property_status')]
    public function changePropertyStatus(
        int                      $id,
        int                      $propertyId,
        ManagerRegistry          $doctrine,
        PropertyStatusRepository $propertyStatusRepository,
        PropertyRepository       $propertyRepository
    ): RedirectResponse
    {
        $em = $doctrine->getManager();

        $status = $propertyStatusRepository->find($id);
        $property = $propertyRepository->find($propertyId);

        if (!$status || !$property) {
            throw $this->createNotFoundException();
        }

        if ($status->getId() === 4) { // retiré
            $property->setPublish(false);
        }

        $property->setPropertyStatus($status);
        $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));
        $em->flush();

        $this->addFlash('success', "Le statut de l'annonce a été modifié avec succès.");

        return $this->redirectToRoute('app_agent_work_space_listing');
    }

    #[Route('/api/request/filter/provinces', name: 'app_filter_provinces', methods: 'POST')]
    public function filterProvince(Request $request, ProvinceRepository $provinceRepository): Response
    {
        $data = json_decode($request->getContent() ?: '{}', false);
        $countryId = (int)($data->country ?? 0);

        $provinces = $provinceRepository->findByCountry($countryId); // keep your custom repo API
        $items = [];
        foreach ($provinces as $p) {
            $items[] = $p->getId() . '-%@#-' . $p->getName();
        }

        return new JsonResponse([
            'provinces' => json_encode($items, JSON_UNESCAPED_UNICODE),
        ], 200, ["Content-Type" => "application/json"]);
    }

    #[Route('/api/request/filter/communes', name: 'app_filter_communes', methods: 'POST')]
    public function filterCommune(Request $request, CommuneRepository $communeRepository, ProvinceRepository $provinceRepository): Response
    {
        $data = json_decode($request->getContent() ?: '{}', false);

        $provinceId = (int)($data->province ?? 0);
        $province = $provinceRepository->find($provinceId);
        $communes = $communeRepository->findBy(['province' => $province]);

        $items = [];
        foreach ($communes as $c) {
            $items[] = $c->getId() . '-%@#-' . $c->getName();
        }

        return new JsonResponse([
            'communes' => json_encode($items, JSON_UNESCAPED_UNICODE),
        ], 200, ["Content-Type" => "application/json"]);
    }

    public function extracted(Property $property): void
    {
        $property->setAddressView(false);
        $property->setFurniture(false);
        $property->setAirCondition(false);
        $property->setPool(false);
        $property->setOpenspaceroof(false);
        $property->setExteriortoilet(false);
        $property->setSecurityguard(false);
        $property->setGarden(false);
        $property->setWifi(false);
        $property->setParking(false);
    }
}

//
//namespace App\Controller;
//
//use App\Entity\Images;
//use App\Entity\Property;
//use App\Entity\PropertyStatus;
//use App\Entity\User;
//use App\Enum\RelationshipToPropertyEnum;
//use App\Form\ImagesType;
//use App\Form\MessageType;
//use App\Repository\CommuneRepository;
//use App\Repository\CountryRepository;
//use App\Repository\ImagesRepository;
//use App\Repository\PropertyRepository;
//use App\Repository\PropertyStatusRepository;
//use App\Repository\ProvinceRepository;
//use App\Repository\ServiceSupRepository;
//use App\Repository\UserRepository;
//use App\Service\AwsS3Service;
//use App\Service\CSRFProtectionService;
//use App\Service\ImageUploaderService;
//use App\Service\PropertyService;
//use Doctrine\Persistence\ManagerRegistry;
//use Liip\ImagineBundle\Imagine\Filter\FilterManager;
//use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
//use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\RequestStack;
//use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\Routing\Attribute\Route;
//use Symfony\Component\Uid\Uuid;
//use Symfony\Component\Validator\Constraints\Country;
//use Liip\ImagineBundle\Service\FilterService;
//use Liip\ImagineBundle\Imagine\Cache\CacheManager;
//use Symfony\Component\HttpFoundation\File\UploadedFile;
//use Spatie\ImageOptimizer\OptimizerChainFactory;
//use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
//
//
//class AgentListingController extends AbstractController
//{
//
//    private ImageUploaderService $imageUploaderService;
//
//    public function __construct(ImageUploaderService $imageUploaderService)
//    {
//        $this->imageUploaderService = $imageUploaderService;
//    }
//
//    #[Route('/agent/listing', name: 'app_agent_listing')]
//    public function index(UserRepository $userRepository, RequestStack $requestStack): Response
//    {
//        $requestStack->getSession()->remove('propertyUuid');
//
//        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
//        if($user == null){
//            return $this->redirectToRoute('app_logout');
//        }
//
//        return $this->render('agent_work_space/listing/new_listing.html.twig', [
//            'controller_name' => 'AgentListingController',
//        ]);
//    }
//
//    #[Route('/agent/listing/details/{uuid}', name: 'app_agent_listing_listing_detail')]
//    public function listingDetail(Property $property): Response
//    {
//        return $this->render('agent_work_space/listing/new_listing.html.twig', [
//            'property' => $property,
//        ]);
//    }
//
//    /**
//     * @throws \Exception
//     */
//    #[Route('/agent/create/new/listing/{code?}', name: 'app_agent_listing_new_listing')]
//    public function newListing(?Property $property, PropertyRepository $propertyRepository, UserRepository $userRepository): Response
//    {
//        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
//        if($user == null){
//            return $this->redirectToRoute('app_logout');
//        }
//
//        if ($property == null){
//            $property = new Property();
//            $property->setId('new');
//            $property->setRegistrationstep(1);
//            $this->extracted($property);
//
//        }else{
//            $property = $propertyRepository->findOneBy(['code' => $property->getCode()]);
//        }
//
//        return $this->render('agent_work_space/listing/new_listing.html.twig', [
//            'user' => $user,
//            'property' => $property,
//            'step' => $property->getRegistrationstep(),
//        ]);
//    }
//
//    #[Route('/agent/listing/property/editing/{code}', name: 'app_agent_listing_property_editing')]
//    public function propertyEditing(Property $property, ManagerRegistry $doctrine): Response
//    {
//        $em = $doctrine->getManager();
//
//        $property->setRegistrationstep(1);
//        $em->flush();
//
//        return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
//    }
//
//
//    /**
//     * @throws \Exception
//     */
//    #[Route('/agent/listing/for/edit/{code}', name: 'app_agent_listing_edit_listing')]
//    public function editListing(Property $property, ManagerRegistry $doctrine, Request $request, CountryRepository $countryRepository, UserRepository $userRepository, ServiceSupRepository $serviceSupRepository, LoaderInterface $binaryLoader, FilterManager $filterManager): Response
//    {
//        $em = $doctrine->getManager();
//        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
//        if($user == null){
//            return $this->redirectToRoute('app_logout');
//        }
//
//        $image = new Images();
//        $form = $this->createForm(ImagesType::class, $image);
//        $form->handleRequest($request);
//        if ($form->isSubmitted() && $form->isValid()) {
//
//            $file = $form['name']->getData();
//
//            if ($file){
//
//                // Upload, resize, optimize and get the S3 URL
//                $imageUrl = $this->imageUploaderService->uploadAndResizeImageToS3($file);
//                $image->setImageUrl($imageUrl);
//
//                $image->setProperty($property);
//                $image->setName(uniqid());
//                $image->setStatus(true);
//
//                $em->persist($image);
//                $em->flush();
//
//                $this->addFlash('success', 'L\'image a été enregistrée avec succès.');
//
//            }
//            return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
//        }
//
//        /* Check is the image is the first image */
//        if ($property->getImage() == null && $property->getImages()[0] != null){
//            $property->setImage($property->getImages()[0]->getImageUrl());
//            $em->flush();
//        }
//
//        $countries = $countryRepository->findAll();
//        $services = $serviceSupRepository->findBy(['user' => $user->getId()]);
//        $serviceInProperty = $serviceSupRepository->getServiceSupFromPropertyId($property->getId());
//
//        return $this->render('agent_work_space/listing/edit_listing.html.twig', [
//            'user' => $user,
//            'property' => $property,
//            'countries' => $countries,
//            'services' => $services,
//            'serviceInProperty' => $serviceInProperty,
//            'formImage' => $form->createView(),
//            'step' => $property->getRegistrationstep(),
//        ]);
//    }
//
//    #[Route('/agent/listing/process/{id}', name: 'app_agent_listing_new_listing_save', methods: ['POST'])]
//    public function studentAdmissionSaveForm($id, Request $request, ManagerRegistry $doctrine, UserRepository $userRepository, PropertyRepository $propertyRepository, PropertyService $ps): Response
//    {
//        $em = $doctrine->getManager();
//        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
//        if($user == null){
//            return $this->redirectToRoute('app_logout');
//        }
//
//        $step = $request->request->get('step');
//
//        $property = $propertyRepository->findOneBy(['id' => $id]);
//        if ($property == null){
//            $property = new Property();
//            $property->setUser($user);
//            $property->setPublish(false);
//            $this->extracted($property);
//
//            $uuid= Uuid::v1();
//            $propertyId= $uuid;
//
//            $property->setUuid($uuid);
//            $property->setUuidProperty($propertyId);
//            $property->setCode(uniqid());
//        }
//
//        $property = $ps->getPropertyFromForm($request, $id, $property, $step);
//
//        if ($step == 1){
//            $property->setRegistrationstep(2);
//        }elseif ($step == 2){
//            $property->setRegistrationstep(3);
//        }elseif ($step == 3) {
//            $property->setRegistrationstep(4);
//        }elseif ($step == 4){
//            $property->setRegistrationstep(5);
//        }elseif ($step == 5){
//            $property->setRegistrationstep(6);
//        }
//
//        $em->persist($property);
//        $em->flush();
//
//        return $this->redirectToRoute('app_agent_listing_edit_listing',['code' => $property->getCode()]);
//    }
//
//    #[Route('/agent/listing/back/button/property/registration/{code?}/{step?}', name: 'app_agent_listing_back_button_admission')]
//    public function backButtonAdmission($code, $step, PropertyRepository $propertyRepository, ManagerRegistry $doctrine): Response
//    {
//        $em = $doctrine->getManager();
//        $property = $propertyRepository->findOneBy(['code'=> $code]);
//
//        if ($step == 2){
//            $property->setRegistrationstep(1);
//        }elseif ($step == 3){
//            $property->setRegistrationstep(2);
//        }elseif ($step == 4){
//            $property->setRegistrationstep(3);
//        }elseif ($step == 5){
//            $property->setRegistrationstep(4);
//        }elseif ($step == 6){
//            $property->setRegistrationstep(5);
//        }
//        $em->flush();
//
//        return $this->redirectToRoute('app_agent_listing_edit_listing',['code' => $property->getCode()]);
//    }
//
//    #[Route('/agent/listing/work/space/publish/listing/{id}', name: 'app_agent_work_space_publish_listing', methods: ['GET', 'POST'])]
//    public function publishProperty(ManagerRegistry $doctrine, CSRFProtectionService $csrf, $id): Response
//    {
//        $em = $doctrine->getManager();
//        $property = $em->getRepository(Property::class)->findOneBy(['id' => $id]);
//
//        return $this->render('agent_work_space/publishing/publish_form.html.twig', [
//            'property' => $property,
//            'token' => $csrf->getCSRFTokenForForm(),
//        ]);
//    }
//
//    #[Route('/agent/listing/work/space/publish/listing/for/save/{id}', name: 'app_agent_work_space_publish_listing_save', methods: ['POST'])]
//    public function savePublishing(Property $property, ManagerRegistry $doctrine, CSRFProtectionService $csrf, Request $request, PropertyStatusRepository $propertyStatusRepository): Response
//    {
//        $error = false;
//        if ($csrf->validateCSRFToken($request)) {
//            if( $property->getImages()->count() > 3){
//                if ($property->getUser()->getAgentPhone() == null){
//                    $error = true;
//                    $this->addFlash('warning', "Veuillez configurer votre numéro agent votre numéro agent.");
//                }else{
//                    $status = $propertyStatusRepository->findOneBy(['id' => 1]);
//
//                    $relationValue = $request->request->get('relationshipToProperty-'.$property->getId());
//                    $property->setRelationshipToProperty(RelationshipToPropertyEnum::from($relationValue));
//
//                    $property->setPublish(true);
//                    $property->setPublishAt(new \DateTime());
//                    $property->setPropertyStatus($status);
//                    $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));
//
//                    $em = $doctrine->getManager();
//                    $em->flush();
//
//                    $this->addFlash('success',  "L'annonce Id: ". $property->getUuidProperty().", vient d'être publié avec succès.");
//                }
//            }else{
//                $error = true;
//                $this->addFlash('warning', "Veuillez finaliser les photos de l'annonce. Minimum. 4 Photos");
//            }
//        }
//
//        return $this->render('feedback.html.twig', [
//            'error' => $error,
//        ]);
//    }
//
//    #[Route('/agent/listing/image/delete/action/for/single/one/{id}', name: 'app_agent_listing_image_action')]
//    public function deleteImage(Images $image, ManagerRegistry $doctrine, ImagesRepository $imagesRepository, PropertyRepository $propertyRepository): RedirectResponse{
//
//        $em = $doctrine->getManager();
//        $image = $imagesRepository->findOneBy(['id' => $image->getId()]);
//        $property = $propertyRepository->findOneBy(['id' => $image->getProperty()]);
//        $em->remove($image);
//        $em->flush();
//        $this->addFlash('success',  "La photo a été retirée avec succès.");
//
//        return $this->redirectToRoute('app_agent_listing_edit_listing', ['code' => $property->getCode()]);
//    }
//
//    #[Route('/agent/listing/change/status/of/a/property/{id}/{propertyId}', name: 'app_agent_listing_change_property_status')]
//    public function changePropertyStatus($id, $propertyId, ManagerRegistry $doctrine, PropertyStatusRepository $propertyStatusRepository, PropertyRepository $propertyRepository): RedirectResponse{
//
//        $em = $doctrine->getManager();
//        $status = $propertyStatusRepository->findOneBy(['id' => $id]);
//        $property = $propertyRepository->findOneBy(['id' => $propertyId]);
//        if ($status->getId() == 4){
//            $property->setPublish(false);
//        }
//        $property->setPropertyStatus($status);
//        $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));
//        $em->flush();
//
//        $this->addFlash('success',  "Le statut de l'annonce a été modifié avec succès.");
//
//        return $this->redirectToRoute('app_agent_work_space_listing');
//    }
//
//    #[Route('/api/request/filter/provinces', name: 'app_filter_provinces', methods: 'POST')]
//    public function filterProvince(Request $request, ProvinceRepository $provinceRepository): Response
//    {
//        $data = json_decode($request->getContent());
//
//        $country_iso = $data->country;
//        $provinces = $provinceRepository->findByCountry($country_iso);
//        $provincesItem = array();
//
//        for ($x = 0; $x <= count($provinces); $x++) {
//            if (isset($provinces[$x])) {
//                $id = $provinces[$x]->getId();
//                $name = $provinces[$x]->getName();
//                $provincesItem[] = $id . '-%@#-' . $name;
//            }
//        }
//        $json = [
//            'provinces' => json_encode($provincesItem),
//        ];
//        return new JsonResponse($json, 200, [
//            "Content-Type" => "application/json"
//        ]);
//    }
//
//    #[Route('/api/request/filter/communes', name: 'app_filter_communes', methods: 'POST')]
//    public function filterCommune(Request $request, CommuneRepository $communeRepository, ProvinceRepository $provinceRepository): Response
//    {
//        $data = json_decode($request->getContent());
//
//        $provinceId = $data->province;
//        $province = $provinceRepository->findOneBy(['id' => $provinceId]);
//        $communes = $communeRepository->findBy(['province' => $province]);
//        $communesItem = array();
//
//        for ($x = 0; $x <= count($communes); $x++) {
//            if (isset($communes[$x])) {
//                $id = $communes[$x]->getId();
//                $name = $communes[$x]->getName();
//                $communesItem[] = $id . '-%@#-' . $name;
//            }
//        }
//        $json = [
//            'communes' => json_encode($communesItem),
//        ];
//        return new JsonResponse($json, 200, [
//            "Content-Type" => "application/json"
//        ]);
//    }
//
//    /**
//     * @param Property $property
//     * @return void
//     */
//    public function extracted(Property $property): void
//    {
//        $property->setAddressView(false);
//        $property->setFurniture(false);
//        $property->setAirCondition(false);
//        $property->setPool(false);
//        $property->setOpenspaceroof(false);
//        $property->setExteriortoilet(false);
//        $property->setSecurityguard(false);
//        $property->setGarden(false);
//        $property->setWifi(false);
//        $property->setParking(false);
//    }
//}
