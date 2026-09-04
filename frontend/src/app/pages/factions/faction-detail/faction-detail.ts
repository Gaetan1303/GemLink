import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Faction, FactionMember, FactionService } from '../../../core/services/faction';
import { ChatService } from '../../../core/services/chat';
import { SharedModule } from '../../../shared/shared-module';

@Component({selector:'app-faction-detail',imports:[SharedModule,RouterLink],templateUrl:'./faction-detail.html',styleUrls:['./faction-detail.scss']})
export class FactionDetail{
  readonly #route=inject(ActivatedRoute);readonly #router=inject(Router);readonly #factions=inject(FactionService);readonly #chat=inject(ChatService);protected readonly faction=signal<Faction|null>(null);protected readonly members=signal<FactionMember[]>([]);protected readonly loading=signal(true);protected readonly actionPending=signal(false);protected readonly error=signal<string|null>(null);protected readonly notice=signal<string|null>(null);protected readonly id=this.#route.snapshot.paramMap.get('id');
  constructor(){if(this.id)this.load();}
  protected load():void{if(!this.id)return;this.loading.set(true);this.#factions.get(this.id).subscribe({next:f=>{this.faction.set(f);this.#factions.members(this.id!).subscribe({next:r=>this.members.set(r.items),error:()=>this.members.set([])});this.loading.set(false);},error:e=>{this.error.set(e.error?.message??'Faction introuvable.');this.loading.set(false);}});}
  protected join():void{
    if(!this.id)return;
    const isPrivate=this.faction()?.visibility==='PRIVATE';
    this.actionPending.set(true);
    const success=()=>{this.notice.set(isPrivate?'Demande envoyée.':'Faction rejointe.');this.actionPending.set(false);this.load();};
    const failure=(error:HttpErrorResponse)=>{this.error.set(error.error?.message??'Action impossible.');this.actionPending.set(false);};
    if(isPrivate)this.#factions.requestJoin(this.id,null).subscribe({next:success,error:failure});
    else this.#factions.join(this.id).subscribe({next:success,error:failure});
  }
  protected leave():void{if(this.id)this.#factions.leave(this.id).subscribe({next:()=>this.load(),error:e=>this.error.set(e.error?.message??'Départ impossible.')});}
  protected openChat():void{if(this.id)this.#chat.faction(this.id).subscribe({next:c=>this.#router.navigate(['/messages',c.id]),error:e=>this.error.set(e.error?.message??'Chat inaccessible.')});}
}
