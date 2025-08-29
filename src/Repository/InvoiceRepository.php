<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findInvoice(Reservation $reservation): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.reservation', 'r')
            ->andWhere('r.id = :reservation')
            ->andWhere('r.confirmed = :confirmed')
            ->setParameter('reservation', $reservation->getId())
            ->setParameter('confirmed', false)
            ->getQuery()
            ->getResult();
    }


} 