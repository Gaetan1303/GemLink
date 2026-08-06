<?php
namespace App\Service;
use App\Entity\AuditLog;use App\Entity\Groupe;use App\Entity\GroupeMember;use App\Entity\User;use App\Repository\GroupeMemberRepository;use Doctrine\ORM\EntityManagerInterface;
class GroupeService { public function __construct(private readonly EntityManagerInterface $em,private readonly GroupeSlugGenerator $slugs,private readonly GroupeMemberRepository $members){}
/** @param array{name:mixed,description?:mixed,visibility?:mixed} $data */ public function create(User $creator,array $data):Groupe{$name=is_string($data['name']??null)?$data['name']:'';$g=new Groupe($name,$this->slugs->generate($name),$creator,is_string($data['visibility']??null)?$data['visibility']:Groupe::VISIBILITY_PUBLIC);$g->setDescription(is_string($data['description']??null)?$data['description']:null);$owner=new GroupeMember($g,$creator,GroupeMember::OWNER);$this->em->persist($g);$this->em->persist($owner);$this->em->flush();return $g;}
/** @param array<string,mixed> $data */ public function update(Groupe $g,array $data):Groupe{if(array_key_exists('name',$data))$g->rename(is_string($data['name'])?$data['name']:'');if(array_key_exists('description',$data))$g->setDescription(is_string($data['description'])?$data['description']:null);if(array_key_exists('visibility',$data))$g->setVisibility(is_string($data['visibility'])?$data['visibility']:'');$this->em->flush();return $g;}
public function archive(Groupe $g,User $actor):void{$g->archive();$this->em->persist(new AuditLog($actor,'FACTION_ARCHIVED','FACTION',$g->getId()));$this->em->flush();}
}
