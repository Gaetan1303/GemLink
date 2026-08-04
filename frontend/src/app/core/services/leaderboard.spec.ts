import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { LeaderboardService } from './leaderboard';

describe('LeaderboardService — US 5.4', () => {
  let service: LeaderboardService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(LeaderboardService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('demande le Top 50 et le rang personnel au même endpoint', () => {
    service.list().subscribe(response => {
      expect(response.items).toHaveLength(1);
      expect(response.currentUser).toEqual({ rank: 67, id: 'me', points: 12 });
    });

    const request = http.expectOne(`${environment.apiUrl}/api/leaderboard`);
    expect(request.request.method).toBe('GET');
    request.flush({
      items: [{ rank: 1, id: 'expert', username: 'Topaze', avatarUrl: null, points: 120, level: 3, trustScore: 80 }],
      total: 73,
      currentUser: { rank: 67, id: 'me', points: 12 },
    });
  });
});
