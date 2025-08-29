<?php

namespace App\Repository;

use App\Entity\Preference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Preference>
 */
class PreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Preference::class);
    }

    public function findOpenLeadsForUser(
        User $user,
        ?string $q = '',
        ?int $cityId = null,
        ?string $type = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        // Get covered city IDs ONCE
        $coveredIds = array_map(fn($c) => $c->getId(), $user->getCoveredCities()->toArray());

        // If no coverage set => return nothing (even for admins)
        if (empty($coveredIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('l')
            ->join('l.city', 'city')->addSelect('city')
            ->andWhere('l.status = :status')->setParameter('status', true)
            ->andWhere('l.deleted = :deleted')->setParameter('deleted', false)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        // Non-admins restricted to covered cities
        if (!$isAdmin) {
            $qb->andWhere('IDENTITY(l.city) IN (:coveredIds)')
                ->setParameter('coveredIds', $coveredIds);
        }

        // City filter: must be within coverage
        if ($cityId !== null) {
            if (!in_array($cityId, $coveredIds, true)) {
                return []; // requested city not covered → nothing
            }
            $qb->andWhere('city.id = :cityId')->setParameter('cityId', $cityId);
        }

        // Type filter (adjust field name if different)
        if ($type !== null && $type !== '') {
            $qb->andWhere('l.propertyType = :ptype')->setParameter('ptype', $type);
        }

        if ($q !== null && $q !== '') {
            $qb->andWhere('(l.title LIKE :q OR l.message LIKE :q)')
                ->setParameter('q', '%'.$q.'%');
        }

        return $qb->getQuery()->getResult();
    }
}
