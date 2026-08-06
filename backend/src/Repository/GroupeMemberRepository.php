<?php
namespace App\Repository;
use App\Entity\Groupe; use App\Entity\GroupeMember; use App\Entity\User; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;
/** @extends ServiceEntityRepository<GroupeMember> */
class GroupeMemberRepository extends ServiceEntityRepository { public function __construct(ManagerRegistry $r){parent::__construct($r,GroupeMember::class);} public function findActive(Groupe $g,User $u):?GroupeMember{return $this->findOneBy(['group'=>$g,'user'=>$u,'status'=>GroupeMember::ACTIVE]);} public function findOwner(Groupe $g):?GroupeMember{return $this->findOneBy(['group'=>$g,'role'=>GroupeMember::OWNER,'status'=>GroupeMember::ACTIVE]);} /** @return GroupeMember[] */ public function findActiveForGroup(Groupe $g):array{return $this->findBy(['group'=>$g,'status'=>GroupeMember::ACTIVE],['role'=>'ASC','joinedAt'=>'ASC']);}}
