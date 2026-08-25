import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export type FactionVisibility = 'PUBLIC' | 'PRIVATE';
export type FactionRole = 'OWNER' | 'ADMIN' | 'MEMBER';
export interface FactionUser { id: string; username: string; avatarUrl: string | null; }
export interface FactionMember { id: string; user: FactionUser; role: FactionRole; status: 'ACTIVE'|'LEFT'|'REMOVED'; joinedAt: string; }
export interface Faction { id:string;name:string;slug:string;description:string|null;visibility:FactionVisibility;status:'ACTIVE'|'ARCHIVED';avatarUrl:string|null;bannerUrl:string|null;memberCount:number;owner:FactionUser|null;membership:{role:FactionRole}|null;permissions:string[];createdAt:string;updatedAt:string; }
export interface FactionJoinRequest { id:string;requester:FactionUser;status:'PENDING'|'ACCEPTED'|'REJECTED'|'CANCELLED';message:string|null;createdAt:string; }
export interface FactionListResponse { items:Faction[];nextCursor:string|null;limit:number; }

@Injectable({providedIn:'root'})
export class FactionService {
  readonly #http=inject(HttpClient); readonly #url=`${environment.apiUrl}/api/factions`;
  list(options:{cursor?:string;search?:string;visibility?:FactionVisibility;membership?:'mine';limit?:number}={}):Observable<FactionListResponse>{let params=new HttpParams().set('limit',options.limit??20);if(options.cursor)params=params.set('cursor',options.cursor);if(options.search)params=params.set('search',options.search);if(options.visibility)params=params.set('visibility',options.visibility);if(options.membership)params=params.set('membership',options.membership);return this.#http.get<FactionListResponse>(this.#url,{params});}
  get(id:string):Observable<Faction>{return this.#http.get<Faction>(`${this.#url}/${id}`);}
  create(data:{name:string;description:string;visibility:FactionVisibility;avatarUrl?:string;bannerUrl?:string}):Observable<Faction>{return this.#http.post<Faction>(this.#url,data);}
  update(id:string,data:Partial<Pick<Faction,'name'|'description'|'visibility'|'avatarUrl'|'bannerUrl'>>):Observable<Faction>{return this.#http.patch<Faction>(`${this.#url}/${id}`,data);}
  members(id:string):Observable<{items:FactionMember[]}>{return this.#http.get<{items:FactionMember[]}>(`${this.#url}/${id}/members`);}
  join(id:string):Observable<{status:'JOINED'}>{return this.#http.post<{status:'JOINED'}>(`${this.#url}/${id}/join`,{});}
  requestJoin(id:string,message:string|null):Observable<FactionJoinRequest>{return this.#http.post<FactionJoinRequest>(`${this.#url}/${id}/join-requests`,{message});}
  cancelRequest(id:string):Observable<void>{return this.#http.delete<void>(`${this.#url}/${id}/join-requests/me`);}
  leave(id:string):Observable<void>{return this.#http.post<void>(`${this.#url}/${id}/leave`,{});}
  requests(id:string):Observable<{items:FactionJoinRequest[]}>{return this.#http.get<{items:FactionJoinRequest[]}>(`${this.#url}/${id}/join-requests`);}
  review(id:string,requestId:string,decision:'accept'|'reject'):Observable<{status:string}>{return this.#http.post<{status:string}>(`${this.#url}/${id}/join-requests/${requestId}/${decision}`,{});}
  removeMember(id:string,userId:string):Observable<void>{return this.#http.delete<void>(`${this.#url}/${id}/members/${userId}`);}
  changeRole(id:string,userId:string,role:'ADMIN'|'MEMBER'):Observable<FactionMember>{return this.#http.patch<FactionMember>(`${this.#url}/${id}/members/${userId}/role`,{role});}
  transferOwnership(id:string,userId:string):Observable<void>{return this.#http.post<void>(`${this.#url}/${id}/transfer-ownership`,{userId});}
  archive(id:string):Observable<void>{return this.#http.delete<void>(`${this.#url}/${id}`);}
}
