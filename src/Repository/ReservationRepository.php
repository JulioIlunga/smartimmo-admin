<?php

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

//    /**
//     * @return Reservation[] Returns an array of Reservation objects
//     */

    public function findByAdmin(User $user, bool $confirmed): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->join('p.user', 'u')
            ->andWhere('u.id = :user')
            ->andWhere('r.confirmed = :confirmed')
            ->setParameter('user', $user->getId())
            ->setParameter('confirmed', $confirmed)
            ->orderBy('r.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    } 
    
    public function findAvailableDate(Property $property, $checkIn, $checkOut): array
    {
        $start = date('Y-m-d', strtotime($checkIn));
        $end = date('Y-m-d', strtotime($checkOut));


        return $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->where('p.id = :property')
            ->andWhere('((r.dateIn >= :start AND r.dateIn < :end AND r.dateOut > :start AND r.dateOut <= :end) OR'
                .'(r.dateIn <= :start AND r.dateOut > :start AND r.dateOut <= :end) OR'
                .'(r.dateIn >= :start AND r.dateIn < :end AND r.dateOut > :end) OR'
                .'(r.dateIn <= :start AND r.dateOut >= :end))')
            ->andWhere('r.confirmed = :confirmed')
            ->setParameter('property', $property->getId())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('confirmed', true)
            ->getQuery()
            ->getResult();
    }

    public function loadReservationsForPeriodForSingleAppartment($startDate, $period, Property $property)
    {
        $start = date('Y-m-d', $startDate);
        $end = date('Y-m-d', $startDate + ($period * 3600 * 24));

        $q = $this
            ->createQueryBuilder('u')
            ->select('u')
            ->where('u.property = :app ')
            ->andWhere('((u.dateIn >= :start AND u.dateOut <= :end) OR'
                .'(u.dateIn < :start AND u.dateOut >= :start) OR'
                .'(u.dateIn <= :end AND u.dateOut > :end) OR'
                .'(u.dateIn < :start AND u.dateOut > :end))')
            ->andWhere('u.confirmed = :confirmed')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('app', $property)
            ->setParameter('confirmed', true)
            ->addOrderBy('u.dateOut', 'ASC')
            ->getQuery();

        $reservations = null;
        try {
            $reservations = $q->getResult();
        } catch (NoResultException $e) {
        }

        return $reservations;
    }

    public function findByUser(User $user, bool $confirmed): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->andWhere('r.user = :user')
            ->andWhere('r.confirmed = :confirmed')
            ->setParameter('user', $user->getId())
            ->setParameter('confirmed', $confirmed)
            ->orderBy('r.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOverReservation(User $user, bool $confirmed, bool $status): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->join('p.user', 'u')
            ->andWhere('u.id = :user')
            ->andWhere('r.confirmed = :confirmed')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user->getId())
            ->setParameter('confirmed', $confirmed)
            ->setParameter('status', $status)
            ->orderBy('r.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

}
