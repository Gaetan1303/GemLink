import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { ReportService } from './report';

describe('ReportService — US 6.1 Signalement de contenu', () => {
  let service: ReportService;
  let http: HttpTestingController;
  const url = `${environment.apiUrl}/api/publications/post-1/reports`;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ReportService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('envoie le motif obligatoire et la description optionnelle', () => {
    service.create('post-1', 'WRONG_IDENTIFICATION', 'Quartz, pas améthyste.').subscribe();

    const request = http.expectOne(url);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({
      reasonType: 'WRONG_IDENTIFICATION',
      description: 'Quartz, pas améthyste.',
    });
    request.flush({ id: 'report-1', status: 'PENDING' });
  });

  it('n’envoie pas une description vide', () => {
    service.create('post-1', 'SPAM', '  ').subscribe();

    const request = http.expectOne(url);
    expect(request.request.body).toEqual({ reasonType: 'SPAM' });
    request.flush({ id: 'report-1', status: 'PENDING' });
  });
});
