import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { LeaderboardService } from '../../core/services/leaderboard';
import { Leaderboard } from './leaderboard';

describe('Leaderboard', () => {
  let fixture: ComponentFixture<Leaderboard>;
  const service = {
    list: vi.fn().mockReturnValue(of({
      items: [{ rank: 1, id: 'user-1', username: 'Topaze', avatarUrl: null, points: 120, level: 3, trustScore: 80 }],
      total: 1,
      currentUser: { rank: 67, id: 'current-user', points: 12 },
    })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Leaderboard],
      providers: [provideRouter([]), { provide: LeaderboardService, useValue: service }],
    }).compileComponents();
    fixture = TestBed.createComponent(Leaderboard);
    fixture.detectChanges();
  });

  it('affiche le classement renvoyé par le service', () => {
    expect(fixture.componentInstance['entries']()).toHaveLength(1);
    expect(fixture.nativeElement.textContent).toContain('Topaze');
    expect(fixture.nativeElement.textContent).toContain('120 points');
  });

  it('affiche la position personnelle quand elle est renvoyée hors du Top 50', () => {
    expect(fixture.nativeElement.querySelector('.my-ranking')?.textContent).toContain('#67');
    expect(fixture.nativeElement.querySelector('.my-ranking')?.textContent).toContain('12');
  });
});
