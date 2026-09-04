<?php

namespace App\Controller;

use App\Entity\Groupe; use App\Entity\GroupeJoinRequest; use App\Entity\GroupeMember; use App\Entity\User;
use App\Repository\GroupeJoinRequestRepository; use App\Repository\GroupeMemberRepository; use App\Repository\GroupeRepository;
use App\Service\ConversationAccessService; use App\Service\ConversationService; use App\Service\GroupeMembershipService; use App\Service\GroupeService;
use Doctrine\ORM\EntityManagerInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\JsonResponse; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\Routing\Attribute\Route; use Symfony\Component\Security\Http\Attribute\IsGranted; use Symfony\Component\Uid\Uuid;

#[Route('/api/factions')]
final class FactionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly GroupeRepository $groups, private readonly GroupeMemberRepository $members, private readonly GroupeJoinRequestRepository $requests, private readonly GroupeService $service, private readonly GroupeMembershipService $membership, private readonly ConversationService $conversations, private readonly ConversationAccessService $conversationAccess) {}

    #[Route('', methods:['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit=max(1,min(50,$request->query->getInt('limit',20))); $visibility=$request->query->get('visibility'); if($visibility!==null&&!in_array($visibility,[Groupe::VISIBILITY_PUBLIC,Groupe::VISIBILITY_PRIVATE],true))return $this->json(['message'=>'Visibilité invalide.'],422);
        $user=$this->getUser(); $mine=$request->query->get('membership')==='mine'; if($mine&&!$user instanceof User)return $this->json(['message'=>'Authentification requise.'],401);
        $items=$this->groups->page($request->query->get('cursor'),$limit,$request->query->get('search'),$visibility,$mine?$user:null); $hasMore=count($items)>$limit; if($hasMore)array_pop($items); $next=$hasMore&&$items!==[]?end($items)->getId()->toRfc4122():null;
        return $this->json(['items'=>array_map(fn(Groupe $g)=>$this->serializeGroup($g,$user instanceof User?$user:null),$items),'nextCursor'=>$next,'limit'=>$limit]);
    }
    #[Route('', methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $r): JsonResponse { try{return $this->json($this->serializeGroup($this->service->create($this->user(),$this->payload($r)),$this->user()),201);}catch(\InvalidArgumentException|\LogicException $e){return $this->json(['message'=>$e->getMessage()],422);} }
    #[Route('/{id}', methods:['GET'])]
    public function show(string $id): JsonResponse { $g=$this->group($id); if(!$g||!$this->isGranted('FACTION_VIEW',$g))return $this->json(['message'=>'Faction introuvable.'],404); return $this->json($this->serializeGroup($g,$this->getUser() instanceof User?$this->getUser():null)); }
    #[Route('/{id}', methods:['PATCH'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(string $id,Request $r):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);if(!$this->isGranted('FACTION_EDIT',$g))return $this->json(['message'=>'Accès refusé.'],403);try{return $this->json($this->serializeGroup($this->service->update($g,$this->payload($r)),$this->user()));}catch(\InvalidArgumentException|\LogicException $e){return $this->json(['message'=>$e->getMessage()],422);}}
    #[Route('/{id}', methods:['DELETE'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function archive(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);if(!$this->isGranted('FACTION_ARCHIVE',$g))return $this->json(['message'=>'Accès refusé.'],403);try{$this->service->archive($g,$this->user());return new JsonResponse(null,204);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/members', methods:['GET'])]
    public function members(string $id):JsonResponse{$g=$this->group($id);if(!$g||!$this->isGranted('FACTION_VIEW',$g))return $this->json(['message'=>'Faction introuvable.'],404);return $this->json(['items'=>array_map($this->serializeMember(...),$this->members->findActiveForGroup($g))]);}
    #[Route('/{id}/join', methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function join(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);try{$this->membership->join($g,$this->user());return $this->json(['status'=>'JOINED'],201);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/join-requests', methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function requestJoin(string $id,Request $r):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);$p=$this->payload($r);try{$x=$this->membership->request($g,$this->user(),is_string($p['message']??null)?$p['message']:null);return $this->json($this->serializeRequest($x),201);}catch(\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/join-requests/me', methods:['DELETE'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cancelRequest(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);try{$this->membership->cancel($g,$this->user());return new JsonResponse(null,204);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/join-requests', methods:['GET'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function requests(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);if(!$this->isGranted('FACTION_MANAGE_REQUESTS',$g))return $this->json(['message'=>'Accès refusé.'],403);return $this->json(['items'=>array_map($this->serializeRequest(...),$this->requests->findPendingForGroup($g))]);}
    #[Route('/{id}/join-requests/{requestId}/{decision}', requirements:['decision'=>'accept|reject'], methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function review(string $id,string $requestId,string $decision):JsonResponse{$g=$this->group($id);$r=$this->joinRequest($requestId);if(!$g||!$r||!$r->getGroup()->getId()->equals($g->getId()))return $this->json(['message'=>'Demande introuvable.'],404);try{if($decision==='accept')$this->membership->accept($r,$this->user());else $this->membership->reject($r,$this->user());return $this->json(['status'=>strtoupper($decision==='accept'?'ACCEPTED':'REJECTED')]);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/leave', methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function leave(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);try{$this->membership->leave($g,$this->user());return new JsonResponse(null,204);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/members/{userId}', methods:['DELETE'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function remove(string $id,string $userId):JsonResponse{$g=$this->group($id);$target=$this->targetUser($userId);if(!$g||!$target)return $this->json(['message'=>'Ressource introuvable.'],404);try{$this->membership->remove($g,$target,$this->user());return new JsonResponse(null,204);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],403);}}
    #[Route('/{id}/members/{userId}/role', methods:['PATCH'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function role(string $id,string $userId,Request $r):JsonResponse{$g=$this->group($id);$target=$this->targetUser($userId);if(!$g||!$target)return $this->json(['message'=>'Ressource introuvable.'],404);$p=$this->payload($r);try{return $this->json($this->serializeMember($this->membership->changeRole($g,$target,is_string($p['role']??null)?$p['role']:'',$this->user())));}catch(\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],403);}}
    #[Route('/{id}/transfer-ownership', methods:['POST'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function transfer(string $id,Request $r):JsonResponse{$g=$this->group($id);$p=$this->payload($r);$target=$this->targetUser(is_string($p['userId']??null)?$p['userId']:'');if(!$g||!$target)return $this->json(['message'=>'Ressource introuvable.'],404);try{$this->membership->transferOwnership($g,$target,$this->user());return new JsonResponse(null,204);}catch(\LogicException $e){return $this->json(['message'=>$e->getMessage()],409);}}
    #[Route('/{id}/conversation', methods:['GET'])] #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function conversation(string $id):JsonResponse{$g=$this->group($id);if(!$g)return $this->json(['message'=>'Faction introuvable.'],404);if(!$this->isGranted('FACTION_ACCESS_CHAT',$g))return $this->json(['message'=>'Accès refusé.'],403);$c=$this->conversations->faction($g,$this->user());$this->conversationAccess->assertAccess($c,$this->user());return $this->json(['id'=>$c->getId()->toRfc4122(),'type'=>$c->getType(),'title'=>$g->getName(),'avatarUrl'=>$g->getAvatarUrl(),'faction'=>['id'=>$g->getId()->toRfc4122(),'name'=>$g->getName()]]);}

    private function group(string $id):?Groupe{try{return $this->groups->find(Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    private function joinRequest(string $id):?GroupeJoinRequest{try{return $this->requests->find(Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    private function targetUser(string $id):?User{try{return $this->em->find(User::class,Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    /** @return array<string,mixed> */ private function payload(Request $r):array{$x=json_decode($r->getContent(),true);return is_array($x)?$x:[];}
    private function user():User{/** @var User $user */$user=$this->getUser();return $user;}
    /** @return array<string,mixed> */
    private function serializeGroup(Groupe $g, ?User $viewer): array
    {
        $membership = $viewer ? $this->members->findActive($g, $viewer) : null;
        $globalModerator = $viewer && in_array($viewer->getRole(), ['ADMIN', 'MODERATOR'], true);
        $restricted = $g->getVisibility() === Groupe::VISIBILITY_PRIVATE && $membership === null && !$globalModerator;
        $owner = $restricted ? null : $this->members->findOwner($g);
        $permissions = [];
        foreach (['FACTION_EDIT','FACTION_ARCHIVE','FACTION_MANAGE_MEMBERS','FACTION_MANAGE_REQUESTS','FACTION_TRANSFER_OWNERSHIP','FACTION_ACCESS_CHAT'] as $permission) {
            if ($this->isGranted($permission, $g)) $permissions[] = $permission;
        }
        return ['id'=>$g->getId()->toRfc4122(),'name'=>$g->getName(),'slug'=>$g->getSlug(),'description'=>$restricted?null:$g->getDescription(),'visibility'=>$g->getVisibility(),'status'=>$g->getStatus(),'avatarUrl'=>$g->getAvatarUrl(),'bannerUrl'=>$restricted?null:$g->getBannerUrl(),'createdAt'=>$g->getCreatedAt()->format(DATE_ATOM),'updatedAt'=>$g->getUpdatedAt()->format(DATE_ATOM),'memberCount'=>$restricted?0:$this->members->countActive($g),'owner'=>$owner?$this->serializeUser($owner->getUser()):null,'membership'=>$membership?['role'=>$membership->getRole()]:null,'permissions'=>$permissions];
    }
    /** @return array<string,mixed> */ private function serializeMember(GroupeMember $m):array{return['id'=>$m->getId()->toRfc4122(),'user'=>$this->serializeUser($m->getUser()),'role'=>$m->getRole(),'status'=>$m->getStatus(),'joinedAt'=>$m->getJoinedAt()->format(DATE_ATOM)];}
    /** @return array<string,mixed> */ private function serializeRequest(GroupeJoinRequest $r):array{return['id'=>$r->getId()->toRfc4122(),'requester'=>$this->serializeUser($r->getRequester()),'status'=>$r->getStatus(),'message'=>$r->getMessage(),'createdAt'=>$r->getCreatedAt()->format(DATE_ATOM)];}
    /** @return array<string,mixed> */ private function serializeUser(User $u):array{return['id'=>$u->getId()->toRfc4122(),'username'=>$u->getUsername(),'avatarUrl'=>$u->getAvatarUrl()];}
}
