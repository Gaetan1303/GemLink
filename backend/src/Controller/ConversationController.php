<?php

namespace App\Controller;

use App\Entity\ChatMessage; use App\Entity\Conversation; use App\Entity\User;
use App\Repository\ChatMessageRepository; use App\Repository\ConversationParticipantRepository;
use App\Service\ChatMessageService; use App\Service\ConversationAccessService; use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\JsonResponse; use Symfony\Component\HttpFoundation\Request; use Symfony\Component\RateLimiter\RateLimiterFactory; use Symfony\Component\Routing\Attribute\Route; use Symfony\Component\Security\Core\Exception\AccessDeniedException; use Symfony\Component\Security\Http\Attribute\IsGranted; use Symfony\Component\Uid\Uuid;

#[Route('/api/conversations')] #[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ConversationController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em,private readonly ConversationService $conversations,private readonly ConversationAccessService $access,private readonly ChatMessageService $messages,private readonly ChatMessageRepository $messageRepository,private readonly ConversationParticipantRepository $participants,private readonly RateLimiterFactory $chatMessageLimiter){}

    #[Route('',methods:['GET'])]
    public function index():JsonResponse{return $this->json(['items'=>array_map(fn(Conversation $c)=>$this->serializeConversation($c),$this->conversations->list($this->user()))]);}
    #[Route('/unread-count',methods:['GET'])]
    public function unreadCount():JsonResponse{$count=0;foreach($this->conversations->list($this->user())as$c)$count+=$this->messages->unread($c,$this->user());return $this->json(['count'=>$count,'unreadCount'=>$count]);}
    #[Route('/direct',methods:['POST'])]
    public function direct(Request $request):JsonResponse{$p=$this->payload($request);$other=$this->userById(is_string($p['userId']??null)?$p['userId']:'');if(!$other)return $this->json(['message'=>'Utilisateur introuvable.'],404);try{$c=$this->conversations->direct($this->user(),$other);return $this->json($this->serializeConversation($c),201);}catch(\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}}
    #[Route('/{id}',methods:['GET'])]
    public function show(string $id):JsonResponse{$c=$this->conversation($id);if(!$c)return $this->json(['message'=>'Conversation introuvable.'],404);if(!$this->access->canAccess($c,$this->user()))return $this->json(['message'=>'Conversation introuvable.'],404);return $this->json($this->serializeConversation($c));}
    #[Route('/{id}/messages',methods:['GET'])]
    public function listMessages(string $id,Request $request):JsonResponse{$c=$this->conversation($id);if(!$c)return $this->json(['message'=>'Conversation introuvable.'],404);$limit=max(1,min(50,$request->query->getInt('limit',30)));$cursor=null;if($request->query->has('cursor')){$cursor=$this->message($request->query->getString('cursor'));if(!$cursor||!$cursor->getConversation()->getId()->equals($c->getId()))return $this->json(['message'=>'Curseur invalide.'],400);}try{$items=$this->messages->page($c,$this->user(),$cursor,$limit);$hasMore=count($items)>$limit;if($hasMore)array_pop($items);$next=$hasMore&&$items!==[]?end($items)->getId()->toRfc4122():null;return $this->json(['items'=>array_map($this->serializeMessage(...),$items),'nextCursor'=>$next,'limit'=>$limit]);}catch(AccessDeniedException){return $this->json(['message'=>'Conversation introuvable.'],404);}}
    #[Route('/{id}/messages',methods:['POST'])]
    public function send(string $id,Request $request):JsonResponse{$c=$this->conversation($id);if(!$c)return $this->json(['message'=>'Conversation introuvable.'],404);$limit=$this->chatMessageLimiter->create($this->user()->getId()->toRfc4122())->consume();if(!$limit->isAccepted())return $this->json(['message'=>'Trop de messages. Réessayez plus tard.'],429,['Retry-After'=>(string)max(1,$limit->getRetryAfter()->getTimestamp()-time())]);$p=$this->payload($request);try{$m=$this->messages->send($c,$this->user(),is_string($p['content']??null)?$p['content']:'');return $this->json($this->serializeMessage($m),201);}catch(AccessDeniedException){return $this->json(['message'=>'Accès refusé.'],403);}catch(\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}}
    #[Route('/{conversationId}/messages/{messageId}',methods:['PATCH'])]
    public function edit(string $conversationId,string $messageId,Request $request):JsonResponse{$pair=$this->messagePair($conversationId,$messageId);if(!$pair)return $this->json(['message'=>'Message introuvable.'],404);[, $m]=$pair;$p=$this->payload($request);try{return $this->json($this->serializeMessage($this->messages->edit($m,$this->user(),is_string($p['content']??null)?$p['content']:'')));}catch(AccessDeniedException){return $this->json(['message'=>'Accès refusé.'],403);}catch(\InvalidArgumentException|\LogicException $e){return $this->json(['message'=>$e->getMessage()],422);}}
    #[Route('/{conversationId}/messages/{messageId}',methods:['DELETE'])]
    public function delete(string $conversationId,string $messageId):JsonResponse{$pair=$this->messagePair($conversationId,$messageId);if(!$pair)return $this->json(['message'=>'Message introuvable.'],404);try{$this->messages->delete($pair[1],$this->user());return new JsonResponse(null,204);}catch(AccessDeniedException){return $this->json(['message'=>'Accès refusé.'],403);}}
    #[Route('/{id}/read',methods:['POST'])]
    public function read(string $id):JsonResponse{$c=$this->conversation($id);if(!$c)return $this->json(['message'=>'Conversation introuvable.'],404);try{$this->messages->markRead($c,$this->user());return new JsonResponse(null,204);}catch(AccessDeniedException){return $this->json(['message'=>'Accès refusé.'],403);}}

    private function serializeConversation(Conversation $c):array{$group=$c->getGroup();$participants=$this->participants->findForConversation($c);$other=null;foreach($participants as$p)if(!$p->getUser()->getId()->equals($this->user()->getId())){$other=$p->getUser();break;}$last=$this->messageRepository->latest($c);return['id'=>$c->getId()->toRfc4122(),'type'=>$c->getType(),'title'=>$group?->getName()??$other?->getUsername()??'Conversation','avatarUrl'=>$group?->getAvatarUrl()??$other?->getAvatarUrl(),'participants'=>array_map(fn($p)=>$this->serializeUser($p->getUser()),$participants),'faction'=>$group?['id'=>$group->getId()->toRfc4122(),'name'=>$group->getName(),'avatarUrl'=>$group->getAvatarUrl()]:null,'lastMessage'=>$last?$this->serializeMessage($last):null,'lastMessageAt'=>$c->getLastMessageAt()?->format(DATE_ATOM),'unreadCount'=>$this->messages->unread($c,$this->user())];}
    private function serializeMessage(ChatMessage $m):array{return['id'=>$m->getId()->toRfc4122(),'content'=>$m->isDeleted()?'Message supprimé':$m->getContent(),'author'=>$this->serializeUser($m->getAuthor()),'createdAt'=>$m->getCreatedAt()->format(DATE_ATOM),'editedAt'=>$m->getEditedAt()?->format(DATE_ATOM),'deletedAt'=>$m->getDeletedAt()?->format(DATE_ATOM)];}
    private function serializeUser(User $u):array{return['id'=>$u->getId()->toRfc4122(),'username'=>$u->getUsername(),'avatarUrl'=>$u->getAvatarUrl()];}
    private function conversation(string $id):?Conversation{try{return $this->em->find(Conversation::class,Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    private function message(string $id):?ChatMessage{try{return $this->em->find(ChatMessage::class,Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    /** @return array{Conversation,ChatMessage}|null */private function messagePair(string $conversationId,string $messageId):?array{$c=$this->conversation($conversationId);$m=$this->message($messageId);return $c&&$m&&$m->getConversation()->getId()->equals($c->getId())?[$c,$m]:null;}
    private function userById(string $id):?User{try{return $this->em->find(User::class,Uuid::fromString($id));}catch(\InvalidArgumentException){return null;}}
    private function user():User{/** @var User $u */$u=$this->getUser();return $u;}
    /** @return array<string,mixed> */private function payload(Request $r):array{$x=json_decode($r->getContent(),true);return is_array($x)?$x:[];}
}
