import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Faction, FactionService } from '../../../core/services/faction';
import { SharedModule } from '../../../shared/shared-module';

@Component({selector:'app-faction-list',imports:[SharedModule,FormsModule,RouterLink],templateUrl:'./faction-list.html',styleUrls:['./faction-list.scss']})
export class FactionList{
  readonly #factions=inject(FactionService);protected readonly factions=signal<Faction[]>([]);protected readonly loading=signal(true);protected readonly error=signal<string|null>(null);protected readonly nextCursor=signal<string|null>(null);protected search='';protected mine=false;
  constructor(){this.load();}
  protected load(cursor?:string):void{this.loading.set(true);this.error.set(null);this.#factions.list({cursor,search:this.search||undefined,membership:this.mine?'mine':undefined}).subscribe({next:p=>{this.factions.set(cursor?[...this.factions(),...p.items]:p.items);this.nextCursor.set(p.nextCursor);this.loading.set(false);},error:e=>{this.error.set(e.error?.message??'Impossible de charger les factions.');this.loading.set(false);}});}
}
