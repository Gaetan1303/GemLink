import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface AppNotification {
  id: string;
  type: 'NEW_LIKE' | 'NEW_COMMENT' | 'LEVEL_UP' | 'BADGE_AWARDED';
  content: string | null;
  isRead: boolean;
  targetId: string | null;
  targetType: string;
  actor: { id: string; username: string } | null;
  createdAt: string;
}

export interface NotificationPage {
  items: AppNotification[];
  unreadCount: number;
}

@Injectable({ providedIn: 'root' })
export class NotificationService {
  readonly #http = inject(HttpClient);
  readonly #url = environment.apiUrl + '/api/notifications';

  list(): Observable<NotificationPage> {
    return this.#http.get<NotificationPage>(this.#url);
  }

  markRead(id: string): Observable<AppNotification> {
    return this.#http.post<AppNotification>(this.#url + '/' + id + '/read', {});
  }

  markAllRead(): Observable<{ unreadCount: number }> {
    return this.#http.post<{ unreadCount: number }>(this.#url + '/read-all', {});
  }
}
