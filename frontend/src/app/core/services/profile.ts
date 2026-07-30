import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface ProfileBadge { id: string; name: string; description: string | null; }
export interface ProfilePost {
  id: string; title: string | null; description: string | null; mediaUrl: string;
  mediaType: 'IMAGE' | 'VIDEO'; createdAt: string;
}
export interface PublicProfile {
  id: string; username: string; avatarUrl: string | null; bio: string | null;
  level: number; badges: ProfileBadge[]; posts: ProfilePost[];
}

@Injectable({
  providedIn: 'root',
})
export class ProfileService {
  readonly #http = inject(HttpClient);
  readonly #apiUrl = `${environment.apiUrl}/api/profiles`;

  getProfile(userId: string): Observable<PublicProfile> {
    return this.#http.get<PublicProfile>(`${this.#apiUrl}/${userId}`);
  }

  updateProfile(userId: string, username: string, bio: string, avatar: File | null): Observable<PublicProfile> {
    const formData = new FormData();
    formData.append('username', username.trim());
    formData.append('bio', bio.trim());
    if (avatar !== null) formData.append('avatar', avatar);
    return this.#http.patch<PublicProfile>(`${this.#apiUrl}/${userId}`, formData);
  }
}
