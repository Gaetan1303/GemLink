<?php

namespace App\Repository;

use App\Entity\Groupe; use App\Entity\GroupeJoinRequest; use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GroupeJoinRequest> */
class GroupeJoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r) { parent::__construct($r, GroupeJoinRequest::class); }
    public function findPending(Groupe $g, User $u): ?GroupeJoinRequest { return $this->findOneBy(['group'=>$g,'requester'=>$u,'status'=>GroupeJoinRequest::PENDING]); }
    /** @return GroupeJoinRequest[] */ public function findPendingForGroup(Groupe $g): array { return $this->findBy(['group'=>$g,'status'=>GroupeJoinRequest::PENDING],['createdAt'=>'ASC']); }
}
