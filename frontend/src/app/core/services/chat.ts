import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export type ConversationType='DIRECT'|'FACTION';
export interface ChatUser{id:string;username:string;avatarUrl:string|null;}
export interface ChatMessage{id:string;content:string;author:ChatUser;createdAt:string;editedAt:string|null;deletedAt:string|null;}
export interface Conversation{id:string;type:ConversationType;title:string;avatarUrl:string|null;participants:ChatUser[];faction:{id:string;name:string;avatarUrl?:string|null}|null;lastMessage:ChatMessage|null;lastMessageAt:string|null;unreadCount:number;}
export interface ConversationListResponse{items:Conversation[];}
export interface ChatMessageListResponse{items:ChatMessage[];nextCursor:string|null;limit:number;}
export interface UnreadCount{count:number;unreadCount:number;}

@Injectable({providedIn:'root'})
export class ChatService{
  readonly #http=inject(HttpClient);readonly #url=`${environment.apiUrl}/api/conversations`;
  list():Observable<ConversationListResponse>{return this.#http.get<ConversationListResponse>(this.#url);}
  get(id:string):Observable<Conversation>{return this.#http.get<Conversation>(`${this.#url}/${id}`);}
  direct(userId:string):Observable<Conversation>{return this.#http.post<Conversation>(`${this.#url}/direct`,{userId});}
  messages(id:string,cursor?:string):Observable<ChatMessageListResponse>{let params=new HttpParams().set('limit',30);if(cursor)params=params.set('cursor',cursor);return this.#http.get<ChatMessageListResponse>(`${this.#url}/${id}/messages`,{params});}
  send(id:string,content:string):Observable<ChatMessage>{return this.#http.post<ChatMessage>(`${this.#url}/${id}/messages`,{content});}
  edit(id:string,messageId:string,content:string):Observable<ChatMessage>{return this.#http.patch<ChatMessage>(`${this.#url}/${id}/messages/${messageId}`,{content});}
  delete(id:string,messageId:string):Observable<void>{return this.#http.delete<void>(`${this.#url}/${id}/messages/${messageId}`);}
  read(id:string):Observable<void>{return this.#http.post<void>(`${this.#url}/${id}/read`,{});}
  unreadCount():Observable<UnreadCount>{return this.#http.get<UnreadCount>(`${this.#url}/unread-count`);}
  faction(id:string):Observable<Conversation>{return this.#http.get<Conversation>(`${environment.apiUrl}/api/factions/${id}/conversation`);}
}
