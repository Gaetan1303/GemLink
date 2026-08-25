<?php

namespace App\Tests\Service;

use App\Entity\Groupe; use App\Entity\GroupeJoinRequest; use App\Entity\GroupeMember; use App\Entity\User;
use App\Repository\GroupeJoinRequestRepository; use App\Repository\GroupeMemberRepository; use App\Service\GroupeMembershipService;
use Doctrine\ORM\EntityManagerInterface; use PHPUnit\Framework\TestCase;

final class GroupeMembershipServiceTest extends TestCase
{
    private function user(): User { return (new User())->setUsername('u'.uniqid())->setEmail(uniqid().'@test.local')->setPasswordHash('x')->setStatus('ACTIVE'); }
    /** @return array{GroupeMembershipService,EntityManagerInterface,GroupeMemberRepository,GroupeJoinRequestRepository} */
    private function service(): array { $em=$this->createMock(EntityManagerInterface::class);$em->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback)=>$callback());$members=$this->createMock(GroupeMemberRepository::class);$requests=$this->createMock(GroupeJoinRequestRepository::class);return[new GroupeMembershipService($em,$members,$requests),$em,$members,$requests]; }
    public function testPublicFactionJoinsImmediately():void{[$service,$em,$members]=$this->service();$group=new Groupe('Quartz Club','quartz-club',$this->user());$members->method('findActive')->willReturn(null);$result=$service->join($group,$this->user());self::assertSame(GroupeMember::MEMBER,$result->getRole());self::assertTrue($result->isActive());}
    public function testPrivateFactionRequiresRequest():void{[$service,,$members,$requests]=$this->service();$group=new Groupe('Quartz Club','quartz-club',$this->user(),Groupe::VISIBILITY_PRIVATE);$members->method('findActive')->willReturn(null);$requests->method('findPending')->willReturn(null);self::assertTrue($service->request($group,$this->user(),'Bonjour')->isPending());}
    public function testDuplicatePendingRequestIsRejected():void{[$service,,$members,$requests]=$this->service();$user=$this->user();$group=new Groupe('Quartz Club','quartz-club',$this->user(),Groupe::VISIBILITY_PRIVATE);$members->method('findActive')->willReturn(null);$requests->method('findPending')->willReturn(new GroupeJoinRequest($group,$user));$this->expectException(\LogicException::class);$service->request($group,$user,null);}
    public function testOwnerCannotLeave():void{[$service,,$members]=$this->service();$owner=$this->user();$group=new Groupe('Quartz Club','quartz-club',$owner);$members->method('findActive')->willReturn(new GroupeMember($group,$owner,GroupeMember::OWNER));$this->expectException(\LogicException::class);$service->leave($group,$owner);}
    public function testAdminCanAcceptRequest():void{[$service,,$members]=$this->service();$owner=$this->user();$admin=$this->user();$requester=$this->user();$group=new Groupe('Quartz Club','quartz-club',$owner,Groupe::VISIBILITY_PRIVATE);$members->method('findActive')->willReturnCallback(fn(Groupe $g,User $u)=>$u===$admin?new GroupeMember($group,$admin,GroupeMember::ADMIN):null);$member=$service->accept(new GroupeJoinRequest($group,$requester),$admin);self::assertSame($requester,$member->getUser());}
    public function testAdminCannotRemoveOwner():void{[$service,,$members]=$this->service();$owner=$this->user();$admin=$this->user();$group=new Groupe('Quartz Club','quartz-club',$owner);$members->method('findActive')->willReturnCallback(fn(Groupe $g,User $u)=>new GroupeMember($group,$u,$u===$owner?GroupeMember::OWNER:GroupeMember::ADMIN));$this->expectException(\LogicException::class);$service->remove($group,$owner,$admin);}
    public function testOwnerTransfersOwnership():void{[$service,,$members]=$this->service();$owner=$this->user();$next=$this->user();$group=new Groupe('Quartz Club','quartz-club',$owner);$ownerMember=new GroupeMember($group,$owner,GroupeMember::OWNER);$nextMember=new GroupeMember($group,$next);$members->method('findActive')->willReturnCallback(fn(Groupe $g,User $u)=>$u===$owner?$ownerMember:$nextMember);$service->transferOwnership($group,$next,$owner);self::assertSame(GroupeMember::ADMIN,$ownerMember->getRole());self::assertSame(GroupeMember::OWNER,$nextMember->getRole());}
}
