<?php

namespace App\Repository;

use App\Entity\LeadClaims;
use App\Entity\Preference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeadClaims>
 */
class LeadClaimsRepository extends ServiceEntityRepository
{
    /** statuses that count as an active claim */
    private const ACTIVE_STATUSES = ['claimed','in_progress','won'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadClaims::class);
    }

    // src/Repository/ClaimRepository.php
    public function findLeadIdsClaimedByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.lead) AS id')
            ->andWhere('c.agent = :agent')->setParameter('agent', $user)
            ->getQuery()->getScalarResult();

        // Flatten to [id, id, ...]
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    public function isLeadClaimedByUser(Preference $lead, User $user): bool
    {
        $cnt = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.lead = :lead')->setParameter('lead', $lead)
            ->andWhere('c.agent = :agent')->setParameter('agent', $user)
            ->getQuery()->getSingleScalarResult();

        return (int)$cnt > 0;
    }

    public function userHasClaimOnLead(User $user, Preference $lead): bool
    {
        $qb = $this->createQueryBuilder('lc')
            ->select('1')
            ->andWhere('lc.lead = :lead')
            ->andWhere('lc.agent = :user')
            ->andWhere('lc.status IN (:statuses)')
            ->setParameter('lead', $lead)
            ->setParameter('user', $user)
            ->setParameter('statuses', self::ACTIVE_STATUSES)
            ->setMaxResults(1);

        return (bool) $qb->getQuery()->getOneOrNullResult();
    }

    public function countForLead($lead): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.lead = :lead')->setParameter('lead', $lead)
            ->getQuery()->getSingleScalarResult();
    }
}
