<?php

namespace App\Repository;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use function Symfony\Component\String\s;

/**
 * @extends ServiceEntityRepository<Property>
 *
 * @method Property|null find($id, $lockMode = null, $lockVersion = null)
 * @method Property|null findOneBy(array $criteria, array $orderBy = null)
 * @method Property[]    findAll()
 * @method Property[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    public function findByFilterForAgent(User $user,int $type, $page = 1, $limit = 20): Paginator
    {

        $q = $this
            ->createQueryBuilder('c')
            ->join('c.user', 'user')
            ->andWhere('user.id = :user')
            ->setParameter('user', $user->getId());

        if ($type == 1){
            $q
                ->andWhere('c.publish = :publish')
                ->setParameter('publish', 1);
        }

        $q
            ->addOrderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($q, $fetchJoinCollection = false);
    }

    public function findByFilterForAgentSearch(User $user, $search, $page = 1, $limit = 20): Paginator
    {

        $q = $this
            ->createQueryBuilder('c')
            ->join('c.user', 'user')
            ->andWhere('user.id = :user')
            ->setParameter('user', $user->getId());

        if ($search !== ''){
            $pieces = explode(" to ", $search);
            $start = $pieces[0];
            $end = $pieces[1];

            $from = date('Y-m-d', strtotime($start));
            $to = date('Y-m-d', strtotime($end));

            $q
                ->andWhere('c.publishAt BETWEEN :from AND :to')
                ->setParameter('from', $from)
                ->setParameter('to', $to);
        }

        $q
            ->addOrderBy('c.publishAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($q, $fetchJoinCollection = false);
    }

    public function findByFilterForAirBnb($checkIn, $checkOut, $type, $city, $commune, $min, $max, $periodicity, $page = 1, $limit = 20): Paginator
    {

        $q = $this->createQueryBuilder('c');

        if($checkIn != '' && $checkOut != ''){

            $start = date('Y-m-d', strtotime($checkIn));
            $end = date('Y-m-d', strtotime($checkOut));

            $q
                ->leftJoin('c.reservations', 'u', 'WITH',
                    'u.confirmed = :confirmed AND u.dateIn < :end AND u.dateOut > :start')
                ->andWhere('u.id IS NULL') // means no overlapping reservation
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->setParameter('confirmed', true);
        }

        return $this->extractedForFilter($type, $q, $city, $commune, $min, $max, $periodicity, $page, $limit);
    }

    public function findByFilter($type, $city, $commune, $min, $max, $periodicity, $page = 1, $limit = 20): Paginator
    {

        $q = $this
            ->createQueryBuilder('c');

        return $this->extractedForFilter($type, $q, $city, $commune, $min, $max, $periodicity, $page, $limit);
    }

    public function findByFilterFromFilters(Request $request, $page = 1, $limit = 20): Paginator
    {
        $q = $this
            ->createQueryBuilder('c')
            ->andWhere('c.publish = :publish')
            ->setParameter('publish', 1);


        if($request->request->get('city') != '' ){
            $q
                ->join('c.propertyProvince', 'province')
                ->andWhere('province.name = :city')
                ->setParameter('city', $request->request->get('city'));
        }

        if($request->request->get('type') != '' ){
            $q
                ->andWhere('c.type LIKE :type')
                ->setParameter('type', $request->request->get('type'));
        }

        if($request->request->get('commune') != 'All' ){
            $q
                ->join('c.commune', 'commune')
                ->andWhere('commune.name = :commune')
                ->setParameter('commune', $request->request->get('commune'));
        }

        $minBudget = $request->request->get('minBudget');
        $maxBudget = $request->request->get('maxBudget');
        if (is_numeric($minBudget)) {
            $q
                ->andWhere('c.price >= :minBudget')
                ->setParameter('minBudget', (float) $minBudget);
        }

        if (is_numeric($maxBudget)) {
            $q
                ->andWhere('c.price <= :maxBudget')
                ->setParameter('maxBudget', (float) $maxBudget);
        }

        if($request->request->get('periodicity') != '' ){
            $q
                ->andWhere('c.periodicity = :periodicity')
                ->setParameter('periodicity', $request->request->get('periodicity'));
        }

        if($request->request->get('furniture') != '' ){
            $q
                ->andWhere('c.furniture = :furniture')
                ->setParameter('furniture', 1);
        }
        if($request->request->get('aircondition') != '' ){
            $q
                ->andWhere('c.airCondition = :air_condition')
                ->setParameter('air_condition', 1);
        }
        if($request->request->get('pool') != '' ){
            $q
                ->andWhere('c.pool = :pool')
                ->setParameter('pool', 1);
        }
        if($request->request->get('roofspace') != '' ){
            $q
                ->andWhere('c.openspaceroof = :roofspace')
                ->setParameter('roofspace', 1);
        }
        if($request->request->get('exteriortoilet') != '' ){
            $q
                ->andWhere('c.exteriortoilet = :exteriortoilet')
                ->setParameter('exteriortoilet', 1);
        }
        if($request->request->get('guard') != '' ){
            $q
                ->andWhere('c.securityguard = :securityguard')
                ->setParameter('securityguard', 1);
        }
        if($request->request->get('garden') != '' ){
            $q
                ->andWhere('c.garden = :garden')
                ->setParameter('garden', 1);
        }
        if($request->request->get('wifi') != '' ){
            $q
                ->andWhere('c.wifi = :wifi')
                ->setParameter('wifi', 1);
        }
        if($request->request->get('parking') != '' ){
            $q
                ->andWhere('c.parking = :parking')
                ->setParameter('parking', 1);
        }



        $q
            ->addOrderBy('c.publishAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($q, $fetchJoinCollection = false);

    }

    public function findOnlineListing(User $user)
    {

        return $this
            ->createQueryBuilder('c')
            ->join('c.user', 'user')
            ->andWhere('user.id = :user')
            ->andWhere('c.publish = :publish')
            ->setParameter('user', $user->getId())
            ->setParameter('publish', 1)
            ->getQuery()
            ->getResult();
    }

    public function findByFilterForAdmin($search, $page = 1, $limit = 20): Paginator
    {

        $q = $this
            ->createQueryBuilder('c');
        if($search != ''){
             $q
                 ->join('c.user', 'user')
                 ->andWhere('user.id = :user')
                 ->setParameter('user', $search);
        }
        $q
            ->andWhere('c.publish = :publish')
            ->setParameter('publish', 1)
            ->addOrderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($q, $fetchJoinCollection = false);
    }


    /**
     * @param $type
     * @param \Doctrine\ORM\QueryBuilder $q
     * @param $city
     * @param $commune
     * @param $min
     * @param $max
     * @param $periodicity
     * @param mixed $page
     * @param mixed $limit
     * @return Paginator
     */
    public function extractedForFilter($type, \Doctrine\ORM\QueryBuilder $q, $city, $commune, $min, $max, $periodicity, mixed $page, mixed $limit): Paginator
    {
        if ($type != '') {
            $q
                ->andWhere('c.type LIKE :type')
                ->setParameter('type', $type);
        }

        if ($city != '') {
            $q
                ->join('c.propertyProvince', 'province')
                ->andWhere('province.name = :city')
                ->setParameter('city', $city);
        }
        if ($commune != '' && $commune != 'All') {
            $q
                ->join('c.commune', 'commune')
                ->andWhere('commune.name = :commune')
                ->setParameter('commune', $commune);
        }

        if ($min != '') {
            $q
                ->andWhere('c.price >= :minBudget')
                ->setParameter('minBudget', $min);
        }
        if ($max != '') {
            $q
                ->andWhere('c.price <= :maxBudget')
                ->setParameter('maxBudget', $max);
        }
        if ($periodicity != '') {
            $q
                ->andWhere('c.periodicity = :periodicity')
                ->setParameter('periodicity', $periodicity);
        }

        $q
            ->join('c.propertyStatus', 'property_status')
            ->andWhere('c.publish = :publish')
            ->setParameter('publish', 1)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $q
//            ->addOrderBy('property_status', 'Disponible')
            ->addSelect("CASE WHEN property_status.name = 'Disponible' THEN 0 ELSE 1 END AS HIDDEN status_order")
            ->addOrderBy('status_order', 'ASC')
            ->addSelect('RAND() as HIDDEN rand')->orderBy('rand');


        return new Paginator($q, $fetchJoinCollection = false);
    }
}
