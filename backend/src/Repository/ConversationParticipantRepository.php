<?php
namespace App\Repository;
use App\Entity\Conversation;use App\Entity\ConversationParticipant;use App\Entity\User;use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;use Doctrine\Persistence\ManagerRegistry;
/** @extends ServiceEntityRepository<ConversationParticipant> */ final class ConversationParticipantRepository extends ServiceEntityRepository{public function __construct(ManagerRegistry $r){parent::__construct($r,ConversationParticipant::class);}public function findFor(Conversation $c,User $u):?ConversationParticipant{return $this->findOneBy(['conversation'=>$c,'user'=>$u]);}/** @return ConversationParticipant[] */public function findForConversation(Conversation $c):array{return $this->findBy(['conversation'=>$c]);}}
