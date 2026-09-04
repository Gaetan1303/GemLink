import { Component, OnDestroy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { Subscription, timer } from 'rxjs';
import { ChatMessage, ChatService, Conversation } from '../../../core/services/chat';
import { AuthService } from '../../../core/services/auth';
import { SharedModule } from '../../../shared/shared-module';

@Component({selector:'app-conversation-detail',imports:[SharedModule,FormsModule],templateUrl:'./conversation-detail.html',styleUrls:['./conversation-detail.scss']})
export class ConversationDetail implements OnDestroy {
  readonly #route=inject(ActivatedRoute);readonly #chat=inject(ChatService);readonly #auth=inject(AuthService);
  protected readonly conversation=signal<Conversation|null>(null);protected readonly messages=signal<ChatMessage[]>([]);protected readonly loading=signal(true);protected readonly sending=signal(false);protected readonly error=signal<string|null>(null);protected readonly nextCursor=signal<string|null>(null);protected readonly id=this.#route.snapshot.paramMap.get('conversationId');protected draft='';private readonly subscriptions=new Subscription();
  constructor(){if(this.id){this.refresh(true);this.subscriptions.add(timer(8000,8000).subscribe(()=>{if(!document.hidden)this.refresh(false);}));}}
  protected refresh(initial:boolean):void{if(!this.id)return;if(initial)this.loading.set(true);this.#chat.get(this.id).subscribe({next:c=>this.conversation.set(c),error:e=>this.error.set(e.error?.message??'Conversation inaccessible.')});this.#chat.messages(this.id).subscribe({next:p=>{this.messages.set([...p.items].reverse());this.nextCursor.set(p.nextCursor);this.loading.set(false);this.#chat.read(this.id!).subscribe();},error:e=>{this.error.set(e.status===403?'Vous n’avez plus accès à cette conversation.':e.error?.message??'Chargement impossible.');this.loading.set(false);}});}
  protected loadOlder():void{const cursor=this.nextCursor();if(!this.id||!cursor)return;this.#chat.messages(this.id,cursor).subscribe({next:p=>{this.messages.set([...p.items].reverse().concat(this.messages()));this.nextCursor.set(p.nextCursor);}});}
  protected send():void{const content=this.draft.trim();if(!this.id||!content||this.sending())return;this.sending.set(true);this.#chat.send(this.id,content).subscribe({next:m=>{this.messages.update(items=>[...items,m]);this.draft='';this.sending.set(false);},error:e=>{this.error.set(e.error?.message??'Envoi impossible.');this.sending.set(false);}});}
  protected canEdit(message:ChatMessage):boolean{return !message.deletedAt&&message.author.id===this.#auth.currentUser()?.id;}
  protected edit(message:ChatMessage):void{if(!this.id)return;const content=prompt('Modifier le message',message.content)?.trim();if(!content)return;this.#chat.edit(this.id,message.id,content).subscribe({next:updated=>this.messages.update(items=>items.map(item=>item.id===updated.id?updated:item)),error:e=>this.error.set(e.error?.message??'Modification impossible.')});}
  protected remove(message:ChatMessage):void{if(this.id&&confirm('Supprimer ce message ?'))this.#chat.delete(this.id,message.id).subscribe({next:()=>this.messages.update(items=>items.map(item=>item.id===message.id?{...item,content:'Message supprimé',deletedAt:new Date().toISOString()}:item)),error:e=>this.error.set(e.error?.message??'Suppression impossible.')});}
  ngOnDestroy():void{this.subscriptions.unsubscribe();}
}
