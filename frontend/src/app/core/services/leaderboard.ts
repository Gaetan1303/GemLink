import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface LeaderboardEntry {
  rank: number;
  id: string;
  username: string;
  avatarUrl: string | null;
  points: number;
  level: number;
  trustScore: number;
}

export interface CurrentLeaderboardUser {
  rank: number;
  id: string;
  points: number;
}

export interface LeaderboardResponse {
  items: LeaderboardEntry[];
  total: number;
  currentUser: CurrentLeaderboardUser | null;
}

@Injectable({ providedIn: 'root' })
export class LeaderboardService {
  private readonly http = inject(HttpClient);

  list(): Observable<LeaderboardResponse> {
    return this.http.get<LeaderboardResponse>(`${environment.apiUrl}/api/leaderboard`);
  }
}
