import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export type ReportReason = 'INAPPROPRIATE_CONTENT' | 'WRONG_IDENTIFICATION' | 'SPAM' | 'HARASSMENT';

export interface ModerationReport {
  id: string;
  reasonType: ReportReason;
  description: string | null;
  status: 'PENDING' | 'ACCEPTED' | 'REJECTED';
  createdAt: string;
  reporter: { id: string; username: string };
  publication: { id: string; title: string | null; mediaUrl: string; author: string };
}

@Injectable({ providedIn: 'root' })
export class ReportService {
  private readonly http = inject(HttpClient);
  private readonly api = `${environment.apiUrl}/api`;

  create(postId: string, reasonType: ReportReason): Observable<{ id: string; status: string }> {
    return this.http.post<{ id: string; status: string }>(`${this.api}/publications/${postId}/reports`, { reasonType });
  }

  list(status = 'PENDING'): Observable<{ items: ModerationReport[] }> {
    return this.http.get<{ items: ModerationReport[] }>(`${this.api}/moderation/reports`, { params: { status } });
  }

  decide(id: string, decision: 'ACCEPTED' | 'REJECTED'): Observable<ModerationReport> {
    return this.http.post<ModerationReport>(`${this.api}/moderation/reports/${id}/decision`, { decision });
  }
}
