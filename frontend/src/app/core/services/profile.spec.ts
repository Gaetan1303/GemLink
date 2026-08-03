import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { environment } from '../../../environments/environment';

import { ProfileService } from './profile';

describe('ProfileService', () => {
  let service: ProfileService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [HttpClientTestingModule] });
    service = TestBed.inject(ProfileService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('loads the authenticated user points history', () => {
    let receivedTotal = -1;
    service.getPoints('user-1').subscribe((points) => receivedTotal = points.total);

    const request = http.expectOne(`${environment.apiUrl}/api/profiles/user-1/points`);
    expect(request.request.method).toBe('GET');
    request.flush({ total: 17, transactions: [] });

    expect(receivedTotal).toBe(17);
  });
});
