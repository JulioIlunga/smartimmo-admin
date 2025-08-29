<?php

namespace App\Controller;

use App\Entity\Agency;
use App\Entity\Commune;
use App\Entity\Province;
use App\Form\AgencyType;
use App\Form\CommuneType;
use App\Form\ProvinceType;
use App\Repository\CountryRepository;
use App\Repository\ProvinceRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/smart/immo')]
class LocationController extends AbstractController
{
    #[Route('/location', name: 'app_location')]
    public function index(ProvinceRepository $provinceRepository): Response
    {
        $cities = $provinceRepository->findAll();
        return $this->render('smart_immo_admin/location/index.html.twig', [
            'cities' => $cities,
        ]);
    }

    #[Route('/location/create/new', name: 'app_location_create_new_location', methods: ['GET', 'POST'])]
    public function createAgency(ManagerRegistry $doctrine, Request $request, CountryRepository $countryRepository): Response
    {
        $province = new Province();
        $form = $this->createForm(ProvinceType::class, $province);
        $form->handleRequest($request);

        $em = $doctrine->getManager();
        if ($form->isSubmitted() && $form->isValid()) {
            $province->setCode(uniqid());

            $country = $countryRepository->findOneBy(['id' => 1]);
            $province->setCountry($country);

            $em->persist($province);
            $em->flush();

            $this->addFlash('success', 'La province a été crée avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('smart_immo_admin/location/new_province.html.twig', [
            'province' => $province,
            'form' => $form->createView()
        ]);
    }
    #[Route('/location/edit/{id}/province', name: 'app_location_edit_province', methods: ['GET', 'POST'])]
    public function editProvince(ManagerRegistry $doctrine, Request $request, Province $province): Response
    {
        $form = $this->createForm(ProvinceType::class, $province);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $doctrine->getManager()->flush();

            $this->addFlash('success', 'La province a été modifié avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('smart_immo_admin/location/edit_province.html.twig', [
            'province' => $province,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/location/add/new/{id}/commune', name: 'app_agency_add_new_commune', methods: ['GET', 'POST'])]
    public function editAgency(ManagerRegistry $doctrine, Request $request, Province $province): Response
    {

        $commune = new Commune();
        $form = $this->createForm(CommuneType::class, $commune);
        $form->handleRequest($request);
        $em = $doctrine->getManager();

        if ($form->isSubmitted() && $form->isValid()) {

            $commune->setProvince($province);

            $em->persist($commune);
            $em->flush();

            $this->addFlash('success', 'La commune a été ajouté avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('smart_immo_admin/location/commune/new_commune.html.twig', [
            'commune' => $commune,
            'province' => $province,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/location/edit/{id}/commune', name: 'app_location_edit_commune', methods: ['GET', 'POST'])]
    public function editCommune(ManagerRegistry $doctrine, Request $request, Commune $commune): Response
    {
        $form = $this->createForm(CommuneType::class, $commune);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $doctrine->getManager()->flush();

            $this->addFlash('success', 'La commune a été modifié avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('smart_immo_admin/location/commune/edit_commune.html.twig', [
            'commune' => $commune,
            'form' => $form->createView(),
        ]);
    }
}
