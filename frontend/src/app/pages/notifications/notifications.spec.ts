import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { NotificationService } from '../../core/services/notification';
import { Notifications } from './notifications';

describe('Notifications', () => {
  let fixture: ComponentFixture<Notifications>;
  const notification = {
    id: 'notification-1', type: 'NEW_LIKE' as const, content: 'Nouveau like', isRead: false,
    targetId: 'post-1', targetType: 'PUBLICATION', actor: null, createdAt: new Date().toISOString(),
  };
  const service = {
    list: vi.fn().mockReturnValue(of({ items: [notification], unreadCount: 1 })),
    markRead: vi.fn().mockReturnValue(of({ ...notification, isRead: true })),
    markAllRead: vi.fn().mockReturnValue(of({ unreadCount: 0 })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Notifications],
      providers: [provideRouter([]), { provide: NotificationService, useValue: service }],
    }).compileComponents();
    fixture = TestBed.createComponent(Notifications);
    fixture.detectChanges();
  });

  it('charge les notifications et le compteur non lu', () => {
    expect(fixture.componentInstance['notifications']()).toHaveLength(1);
    expect(fixture.componentInstance['unreadCount']()).toBe(1);
  });

  it('marque toutes les notifications comme lues', () => {
    fixture.componentInstance['markAllRead']();
    expect(service.markAllRead).toHaveBeenCalled();
    expect(fixture.componentInstance['unreadCount']()).toBe(0);
  });
});
