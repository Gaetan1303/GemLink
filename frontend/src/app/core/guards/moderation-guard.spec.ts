import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router, UrlTree } from '@angular/router';
import { AuthService, User } from '../services/auth';
import { moderationGuard } from './moderation-guard';

describe('moderationGuard', () => {
  const currentUser = signal<User | null>({ id: 'moderator-1', username: 'Opale', role: 'ROLE_MODERATOR' });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: AuthService, useValue: { currentUser } }],
    });
  });

  it.each(['ROLE_MODERATOR', 'ROLE_ADMIN'] as const)('autorise le rôle %s', role => {
    currentUser.set({ id: 'user-1', username: 'Opale', role });
    const result = TestBed.runInInjectionContext(() => moderationGuard({} as never, {} as never));
    expect(result).toBe(true);
  });

  it('redirige un utilisateur standard vers la connexion', () => {
    currentUser.set({ id: 'user-1', username: 'Quartz', role: 'ROLE_USER' });
    const result = TestBed.runInInjectionContext(() => moderationGuard({} as never, {} as never));
    expect(result).toBeInstanceOf(UrlTree);
    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/auth/login');
  });
});
