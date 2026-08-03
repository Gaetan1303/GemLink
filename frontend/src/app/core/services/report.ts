import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export type ReportReason = 'INAPPROPRIATE_CONTENT' | 'WRONG_IDENTIFICATION' | 'SPAM' | 'HARASSMENT';
export type ModerationDecision = 'ACCEPTED' | 'REJECTED';

export interface ModerationReasonDetail {
  reportId: string;
  reasonType: ReportReason;
  description: string | null;
  reporter: { id: string; username: string };
  createdAt: string;
}

export interface ModerationHistoryEntry {
  id: string;
  moderator: { id: string; username: string };
  action: 'REPORT_ACCEPTED' | 'REPORT_REJECTED';
  target: { type: string; id: string };
  reason: string | null;
  createdAt: string;
}

export interface ModerationReport {
  id: string;
  reasonType: ReportReason;
  description: string | null;
  status: 'PENDING' | 'ACCEPTED' | 'REJECTED';
  createdAt: string;
  reportCount: number;
  reasonDetails: ModerationReasonDetail[];
  moderationHistory: ModerationHistoryEntry[];
  reporter: { id: string; username: string };
  publication: {
    id: string;
    title: string | null;
    mediaUrl: string;
    status: string;
    deletedAt: string | null;
    author: string;
  };
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

  decide(id: string, decision: ModerationDecision, reason?: string): Observable<ModerationReport> {
    return this.http.post<ModerationReport>(`${this.api}/moderation/reports/${id}/decision`, {
      decision,
      ...(reason ? { reason } : {}),
    });
  }
}
