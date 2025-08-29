<?php


namespace App\Service;

use App\Entity\Commune;
use App\Entity\Country;
use App\Entity\Property;
use App\Entity\Province;
use App\Entity\ServiceSup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class PropertyService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getPropertyFromForm(Request $request, string|int $id, Property $property, int $step): Property
    {
        return $this->mapFromRequest($request, (string)$id, $property, (int)$step);
    }

    /**
     * Map step-specific fields from Request into Property.
     */
    private function mapFromRequest(Request $request, string $id, Property $property, int $step): Property
    {
        switch ($step) {
            case 1:
                $property->setHeader($request->request->get("header-$id"));
                $property->setTitle($request->request->get("title-$id"));
                $property->setType($request->request->get("type-$id"));
                $property->setTypeLocation($request->request->get("typeLocation-$id"));
                $property->setCurrency('USD');

                $price = $request->request->get("price-$id");
                $property->setPrice($price !== null ? (float)$price : 0.0);

                $periodicity = $request->request->get("periodicity-$id") ?: 'Monthly';
                $property->setPeriodicity($periodicity);

                if ($periodicity === 'Daily') {
                    // booking percentage applies, visit price cleared
                    $percentage = $request->request->get("percentage-$id");
                    $property->setPourcerntageOfBooking($percentage !== null ? (string)$percentage : null);
                    $property->setPriceOfVisit(null);
                } else { // Monthly
                    $property->setPourcerntageOfBooking(null);
                    $visit = $request->request->get("priceOfVisit-$id");
                    $property->setPriceOfVisit($visit !== null ? round((float)$visit, 2) : null);
                }
                break;

            case 2:
                $countryId = $request->request->get("country-$id");
                $provinceId = $request->request->get("province-$id");
                $communeId = $request->request->get("commune-$id");

                $country = $countryId ? $this->em->getRepository(Country::class)->find($countryId) : null;
                $province = $provinceId ? $this->em->getRepository(Province::class)->find($provinceId) : null;
                $commune = $communeId ? $this->em->getRepository(Commune::class)->find($communeId) : null;

                $property->setPropertyCountry($country);
                $property->setPropertyProvince($province);
                $property->setCommune($commune);

                $address = $request->request->get("address-$id");
                $property->setAdress($address);
                $property->setCity($province?->getName() ?? null);

                // Address view only if checkbox on AND address provided
                $addressViewChecked = $request->request->get("adressview-$id");
                $property->setAddressView(!empty($addressViewChecked) && !empty($address));
                break;

            case 3:
                $surface = $request->request->get("surfaceArea-$id");
                $guests = $request->request->get("guests-$id");
                $beds = $request->request->get("bedroom-$id");
                $baths = $request->request->get("bathroom-$id");
                $living = $request->request->get("livingroom-$id");

                $property->setSurfaceArea($surface !== null ? (float)$surface : 0.0);
                $property->setGuests((string)($guests ?? '0'));
                $property->setBedroom((string)($beds ?? '0'));
                $property->setBathroom((string)($baths ?? '0'));
                $property->setLivingroom((string)($living ?? '0'));
                break;

            case 4:
                $selected = $request->request->all('services-'.$id); // array or []
                if (!\is_array($selected)) {
                    // just in case, normalize to array
                    $selected = $selected !== null ? [$selected] : [];
                }

                // Clear previous
                $propertyFromService = $this->em->getRepository(ServiceSup::class)->getServiceSupFromPropertyId($property->getId());
                foreach ($propertyFromService as $serviceSup) {
                    $property->removeServiceSup($serviceSup);
                }

                // Re-add selected
                foreach ($selected as $serviceId) {
                    if ($service = $this->em->getRepository(ServiceSup::class)->find($serviceId)) {
                        $property->addServiceSup($service);
                    }
                }
                $this->em->flush();

                // Booleans from checkboxes
                $property->setFurniture($this->boolParam($request, "furniture-$id"));
                $property->setAirCondition($this->boolParam($request, "aircondition-$id"));
                $property->setPool($this->boolParam($request, "pool-$id"));
                $property->setOpenspaceroof($this->boolParam($request, "roofspace-$id"));
                $property->setExteriortoilet($this->boolParam($request, "exteriortoilet-$id"));
                $property->setSecurityguard($this->boolParam($request, "securityguard-$id"));
                $property->setGarden($this->boolParam($request, "garden-$id"));
                $property->setWifi($this->boolParam($request, "wifi-$id"));
                $property->setParking($this->boolParam($request, "parking-$id"));
                break;
        }

        return $property;
    }

    /**
     * Treats common checkbox values (on/1/true) as boolean true.
     */
    private function boolParam(Request $request, string $key): bool
    {
        $val = $request->request->get($key);
        if ($val === null) {
            return false;
        }
        // Accept typical truthy checkbox values
        return in_array(strtolower((string)$val), ['1', 'on', 'true', 'yes'], true);
    }
}

//
//namespace App\Service;
//
//use App\Entity\Commune;
//use App\Entity\Country;
//use App\Entity\Property;
//use App\Entity\Province;
//use App\Entity\ServiceSup;
//use Doctrine\ORM\EntityManagerInterface;
//use Symfony\Component\HttpFoundation\Request;
//
//class PropertyService
//{
//    private EntityManagerInterface $em;
//
//    public function __construct(EntityManagerInterface $em)
//    {
//        $this->em = $em;
//    }
//    public function getPropertyFromForm(Request $request, $id, $property, $step): Property
//    {
//        return $this->extractedPropertyParameter($request, $id, $property, $step);
//    }
//    /**
//     * @param Request $request
//     * @param $id
//     * @param mixed $property
//     * @param $step
//     * @return mixed
//     */
//    public function extractedPropertyParameter(Request $request, $id, mixed $property, $step): mixed
//    {
//
//        if ($step == 1){
//
//            $property->setHeader($request->request->get('header-'.$id));
//            $property->setTitle($request->request->get('title-'.$id));
//            $property->setType($request->request->get('type-'.$id));
//            $property->setTypeLocation($request->request->get('typeLocation-'.$id));
//            $property->setCurrency('USD');
//            $property->setPrice($request->request->get('price-'.$id));
//
//            if ($request->request->get('periodicity-'.$id) == null){
//                $property->setPeriodicity('Monthly');
//            }else{
//                $property->setPeriodicity($request->request->get('periodicity-'.$id));
//            }
//            if($request->request->get('periodicity-'.$id) == 'Daily' ){
//                $property->setPourcerntageOfBooking($request->request->get('percentage-'.$id));
//                $property->setPriceOfVisit(null);
//            }else if($request->request->get('periodicity-'.$id) == 'Monthly'){
//                $property->setPourcerntageOfBooking(null);
//                $property->setPriceOfVisit(number_format((float) $request->request->get('priceOfVisit-'.$id), 2, '.', ''));
//            }
//
//        }elseif ($step == 2){
//
//            $country = $this->em->getRepository(Country::class)->find($request->request->get('country-'.$id));
//            $property->setPropertyCountry($country);
//
//            $province = $this->em->getRepository(Province::class)->find($request->request->get('province-'.$id));
//            $property->setPropertyProvince($province);
//
//            $commune = $this->em->getRepository(Commune::class)->find($request->request->get('commune-'.$id));
//            $property->setCommune($commune);
//
//            $property->setAdress($request->request->get('address-'.$id));
//            $property->setCity($province->getName());
//
//            if (null != $request->request->get('adressview-'.$id) and $property->getAdress() != null) {
//                $property->setAddressView(true);
//            }else{
//                $property->setAddressView(false);
//            }
//
//        }elseif ($step == 3){
//
//            $property->setSurfaceArea(floatval($request->request->get('surfaceArea-'.$id)));
//            $property->setGuests(strval($request->request->get('guests-'.$id)));
//            $property->setBedroom(strval($request->request->get('bedroom-'.$id)));
//            $property->setBathroom(strval($request->request->get('bathroom-'.$id)));
//            $property->setLivingroom(strval($request->request->get('livingroom-'.$id)));
//
//            if ($property->getGuests() == null){ $property->setGuests('0');}
//            if ($property->getBedroom() == null){ $property->setBedroom('0');}
//            if ($property->getBathroom() == null){ $property->setBathroom('0');}
//            if ($property->getLivingroom() == null){ $property->setLivingroom('0');}
//
//        }elseif ($step == 4){
//
//            $propertyFromService = $this->em->getRepository(ServiceSup::class)->getServiceSupFromPropertyId($property->getId());
//
//            foreach ($propertyFromService as $serviceSup){
//                $property->removeServiceSup($serviceSup);
//            }
//
//            $servicesTypes = $request->request->all('services-'.$id) ?? [];
//            $serviceCount = count($servicesTypes);
//
//            for ($i = 0; $i < $serviceCount; ++$i) {
//                $service = $this->em->getRepository(ServiceSup::class)->find($servicesTypes[$i]);
//                if ($service) {
//                    $property->addServiceSup($service);
//                    $this->em->flush();
//                }
//            }
//
//
//            if ($request->request->get('furniture-'.$id) != null){ $property->setFurniture(true);} else { $property->setFurniture(false);}
//            if ($request->request->get('aircondition-'.$id) != null){ $property->setAirCondition(true);} else { $property->setAirCondition(false);}
//            if ($request->request->get('pool-'.$id) != null){ $property->setPool(true);} else { $property->setPool(false);}
//            if ($request->request->get('roofspace-'.$id) != null){ $property->setOpenspaceroof(true);} else { $property->setOpenspaceroof(false);}
//            if ($request->request->get('exteriortoilet-'.$id) != null){ $property->setExteriortoilet(true);} else { $property->setExteriortoilet(false);}
//            if ($request->request->get('securityguard-'.$id) != null){ $property->setSecurityguard(true);} else { $property->setSecurityguard(false);}
//            if ($request->request->get('garden-'.$id) != null){ $property->setGarden(true);} else { $property->setGarden(false);}
//            if ($request->request->get('wifi-'.$id) != null){ $property->setWifi(true);} else { $property->setWifi(false);}
//            if ($request->request->get('parking-'.$id) != null){ $property->setParking(true);} else { $property->setParking(false);}
//
//        }
//
//        return $property;
//    }
//
//}