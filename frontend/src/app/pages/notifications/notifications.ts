import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AppNotification, NotificationService } from '../../core/services/notification';

@Component({
  selector: 'app-notifications',
  imports: [CommonModule, RouterLink],
  templateUrl: './notifications.html',
  styleUrl: './notifications.scss',
})
export class Notifications implements OnInit {
  private readonly service = inject(NotificationService);

  protected readonly notifications = signal<AppNotification[]>([]);
  protected readonly unreadCount = signal(0);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.service.list().subscribe({
      next: ({ items, unreadCount }) => {
        this.notifications.set(items);
        this.unreadCount.set(unreadCount);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger les notifications.');
        this.loading.set(false);
      },
    });
  }

  protected markRead(notification: AppNotification): void {
    if (notification.isRead) return;
    this.service.markRead(notification.id).subscribe({
      next: (updated) => {
        this.notifications.update(items => items.map(item => item.id === updated.id ? updated : item));
        this.unreadCount.update(value => Math.max(0, value - 1));
      },
    });
  }

  protected markAllRead(): void {
    this.service.markAllRead().subscribe({
      next: () => {
        this.notifications.update(items => items.map(item => ({ ...item, isRead: true })));
        this.unreadCount.set(0);
      },
    });
  }
}
