<?php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function getAverageRatingForAgent(int $agentId): ?float
    {
        return $this->createQueryBuilder('r')
            ->select('AVG(r.score)')
            ->andWhere('r.agent = :agentId')
            ->setParameter('agentId', $agentId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAverageRatingsForAgents(array $agents): array
    {
        $agentIds = array_map(function($agent) {
            return $agent->getId();
        }, $agents);

        $results = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.agent) as agentId, AVG(r.score) as averageRating')
            ->where('r.agent IN (:agentIds)')
            ->setParameter('agentIds', $agentIds)
            ->groupBy('r.agent')
            ->getQuery()
            ->getResult();

        $ratings = [];
        foreach ($results as $result) {
            $ratings[$result['agentId']] = (float) $result['averageRating'];
        }

        return $ratings;
    }

    public function getRatingCountsForAgents(array $agents): array
    {
        $agentIds = array_map(function($agent) {
            return $agent->getId();
        }, $agents);

        $results = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.agent) as agentId, COUNT(r.id) as ratingCount')
            ->where('r.agent IN (:agentIds)')
            ->setParameter('agentIds', $agentIds)
            ->groupBy('r.agent')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['agentId']] = (int) $result['ratingCount'];
        }

        return $counts;
    }

    public function getReviewsForAgent(int $agentId, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('r')
            ->andWhere('r.agent = :agentId')
            ->andWhere('r.comment IS NOT NULL')
            ->andWhere('r.comment <> :empty')
            ->setParameter('agentId', $agentId)
            ->setParameter('empty', '')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countReviewsForAgent(int $agentId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.agent = :agentId')
            ->andWhere('r.comment IS NOT NULL')
            ->andWhere('r.comment <> :empty')
            ->setParameter('agentId', $agentId)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();
    }


//    /**
//     * @return Rating[] Returns an array of Rating objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Rating
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
