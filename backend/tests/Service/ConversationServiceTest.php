<?php

namespace App\Tests\Service;

use App\Entity\Conversation; use App\Entity\Groupe; use App\Entity\User; use App\Repository\ConversationRepository; use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface; use PHPUnit\Framework\TestCase;

final class ConversationServiceTest extends TestCase
{
    private function user():User{return(new User())->setUsername('u'.uniqid())->setEmail(uniqid().'@test.local')->setPasswordHash('x')->setStatus('ACTIVE');}
    /** @return array{ConversationService,EntityManagerInterface,ConversationRepository} */private function service():array{$em=$this->createMock(EntityManagerInterface::class);$em->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback)=>$callback());$repo=$this->createMock(ConversationRepository::class);return[new ConversationService($em,$repo),$em,$repo];}
    public function testCannotOpenConversationWithSelf():void{[$service]=$this->service();$user=$this->user();$this->expectException(\InvalidArgumentException::class);$service->direct($user,$user);}
    public function testInactiveTargetIsRejected():void{[$service]=$this->service();$other=$this->user()->setStatus('BANNED');$this->expectException(\InvalidArgumentException::class);$service->direct($this->user(),$other);}
    public function testExistingDirectConversationIsReturned():void{[$service,$em,$repo]=$this->service();$author=$this->user();$other=$this->user();$existing=new Conversation(Conversation::DIRECT,$author,null,$this->key($author,$other));$repo->method('findDirect')->willReturn($existing);$em->expects($this->never())->method('persist');self::assertSame($existing,$service->direct($author,$other));}
    public function testDirectCreationIsOrderIndependent():void{[$service,,$repo]=$this->service();$a=$this->user();$b=$this->user();$keys=[];$repo->method('findDirect')->willReturnCallback(function(string $key)use(&$keys){$keys[]=$key;return new Conversation(Conversation::DIRECT,null,null,$key);});$service->direct($a,$b);$service->direct($b,$a);self::assertSame($keys[0],$keys[1]);}
    public function testFactionConversationIsIdempotent():void{[$service,$em,$repo]=$this->service();$owner=$this->user();$group=new Groupe('Quartz Club','quartz-club',$owner);$existing=new Conversation(Conversation::FACTION,$owner,$group);$repo->method('findFaction')->willReturn($existing);$em->expects($this->never())->method('persist');self::assertSame($existing,$service->faction($group,$owner));}
    private function key(User $a,User $b):string{$ids=[$a->getId()->toRfc4122(),$b->getId()->toRfc4122()];sort($ids,SORT_STRING);return implode(':',$ids);}
}
