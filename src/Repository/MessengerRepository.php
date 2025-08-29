<?php

namespace App\Repository;

use App\Entity\Messenger;
use App\Entity\Property;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Messenger>
 */
class MessengerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Messenger::class);
    }

    public function findMessengersReceived(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.agent', 'agent')
            ->where('agent.id = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult()
            ;
    }
    public function findMessengersSent(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.client', 'client')
            ->where('client.id = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findMessages(User $agent, User $user, Property $property)
    {
        return $this->createQueryBuilder('m')
            ->join('m.receiver', 'r')
            ->join('m.sender', 's')
            ->join('m.property', 'p')
            ->andWhere('r.id = :agent')
            ->andWhere('s.id = :sender')
            ->andWhere('p.id = :property')
            ->setParameter('agent', $agent->getId())
            ->setParameter('sender', $user->getId())
            ->setParameter('property', $property->getId())
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }

    //    /**
    //     * @return Messenger[] Returns an array of Messenger objects
    //     */
//        public function findByExampleField($value): array
//        {
//            return $this->createQueryBuilder('m')
//                ->andWhere('m.exampleField = :val')
//                ->setParameter('val', $value)
//                ->orderBy('m.id', 'ASC')
//                ->setMaxResults(10)
//                ->getQuery()
//                ->getResult()
//            ;
//        }

    //    public function findOneBySomeField($value): ?Messenger
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
