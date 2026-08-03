import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface ProfileBadge { id: string; name: string; description: string | null; }
export interface ProfilePost {
  id: string; title: string | null; description: string | null; mediaUrl: string;
  mediaType: 'IMAGE' | 'VIDEO'; createdAt: string;
}
export interface LevelProgression {
  level: number;
  name: string;
  minPoints: number;
  nextLevel: number | null;
  nextLevelName: string | null;
  nextLevelMinPoints: number | null;
  progressPercent: number;
}
export interface PublicProfile {
  id: string; username: string; avatarUrl: string | null; bio: string | null;
  trustScore: number; level: number; progression: LevelProgression; badges: ProfileBadge[]; posts: ProfilePost[];
}

export type PointsAction =
  | 'POST_CREATED'
  | 'LIKE_RECEIVED'
  | 'VALIDATION_SUBMITTED'
  | 'VALIDATION_CONSENSUS_CONFIRMED';

export interface PointsTransaction {
  action: PointsAction;
  amount: number;
  date: string;
}

export interface ProfilePoints {
  total: number;
  transactions: PointsTransaction[];
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

  getPoints(userId: string): Observable<ProfilePoints> {
    return this.#http.get<ProfilePoints>(`${this.#apiUrl}/${userId}/points`);
  }

  updateProfile(userId: string, username: string, bio: string, avatar: File | null): Observable<PublicProfile> {
    const formData = new FormData();
    formData.append('username', username.trim());
    formData.append('bio', bio.trim());
    if (avatar !== null) formData.append('avatar', avatar);
    return this.#http.patch<PublicProfile>(`${this.#apiUrl}/${userId}`, formData);
  }
}
