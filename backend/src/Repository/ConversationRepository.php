<?php

namespace App\Repository;

use App\Entity\Conversation; use App\Entity\Groupe; use App\Entity\GroupeMember; use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Conversation> */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r) { parent::__construct($r, Conversation::class); }
    public function findDirect(string $key): ?Conversation { return $this->findOneBy(['directKey'=>$key]); }
    public function findFaction(Groupe $g): ?Conversation { return $this->findOneBy(['group'=>$g,'type'=>Conversation::FACTION]); }
    /** @return Conversation[] */
    public function findForUser(User $u): array
    {
        return $this->createQueryBuilder('c')->distinct()->addSelect('g')
            ->leftJoin('c.group', 'g')
            ->leftJoin('App\Entity\ConversationParticipant', 'p', 'WITH', 'p.conversation=c AND p.user=:user')
            ->leftJoin(GroupeMember::class, 'gm', 'WITH', 'gm.group=c.group AND gm.user=:user AND gm.status=:active')
            ->andWhere('(c.type=:direct AND p.id IS NOT NULL) OR (c.type=:faction AND gm.id IS NOT NULL AND g.status=:groupActive)')
            ->setParameters(['user'=>$u,'active'=>GroupeMember::ACTIVE,'groupActive'=>Groupe::STATUS_ACTIVE,'direct'=>Conversation::DIRECT,'faction'=>Conversation::FACTION])
            ->orderBy('c.lastMessageAt','DESC')->addOrderBy('c.createdAt','DESC')->getQuery()->getResult();
    }
}
