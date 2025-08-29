<?php

namespace App\Repository;

use App\Entity\ServiceSup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceSup>
 */
class ServiceSupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceSup::class);
    }

    public function getServiceSupFromPropertyId(int $propertyId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.properties', 'p')
            ->where('p.id = :id')
            ->setParameter('id', $propertyId)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return ServiceSup[] Returns an array of ServiceSup objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ServiceSup
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
