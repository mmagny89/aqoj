<?php

namespace App\Repository;

use App\Entity\MechanicMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MechanicMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MechanicMapping::class);
    }

    /** @return array<string, string>  bggMechanic => engelsteinFamily */
    public function getFamilyMap(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.bggMechanic', 'm.engelsteinFamily')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['bggMechanic']] = $row['engelsteinFamily'];
        }

        return $map;
    }
}
