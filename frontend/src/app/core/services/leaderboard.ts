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

@Injectable({ providedIn: 'root' })
export class LeaderboardService {
  private readonly http = inject(HttpClient);

  list(): Observable<{ items: LeaderboardEntry[]; total: number }> {
    return this.http.get<{ items: LeaderboardEntry[]; total: number }>(`${environment.apiUrl}/api/leaderboard`);
  }
}
