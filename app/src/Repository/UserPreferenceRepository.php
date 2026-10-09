<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    public function findOrCreate(User $user): UserPreference
    {
        $pref = $this->find($user->getId());
        if ($pref === null) {
            $pref = new UserPreference($user);
        }
        return $pref;
    }
}
