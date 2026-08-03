import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { Admin, AdminLevelPayload } from './admin';

describe('Admin level management', () => {
  let service: Admin;
  let http: HttpTestingController;
  const apiUrl = `${environment.apiUrl}/api/admin/levels`;
  const payload: AdminLevelPayload = {
    number: 3,
    name: 'Connaisseur',
    minPoints: 500,
    badgeId: null,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(Admin);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('loads configured levels', () => {
    service.getLevels().subscribe((levels) => expect(levels[0].name).toBe('Novice'));

    const request = http.expectOne(apiUrl);
    expect(request.request.method).toBe('GET');
    request.flush([{ id: 'level-1', number: 1, name: 'Novice', minPoints: 0, badgeId: null }]);
  });

  it('creates a level with its optional badge association', () => {
    service.createLevel({ ...payload, badgeId: 'badge-1' }).subscribe();

    const request = http.expectOne(apiUrl);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ ...payload, badgeId: 'badge-1' });
    request.flush({ id: 'level-3', ...payload, badgeId: 'badge-1' });
  });

  it('updates an existing threshold without changing its number', () => {
    const update = { name: 'Connaisseur confirmé', minPoints: 600, badgeId: null };
    service.updateLevel('level-3', update).subscribe();

    const request = http.expectOne(`${apiUrl}/level-3`);
    expect(request.request.method).toBe('PATCH');
    expect(request.request.body).toEqual(update);
    request.flush({ id: 'level-3', number: 3, ...update });
  });

  it('deletes a configured level', () => {
    service.deleteLevel('level-3').subscribe();

    const request = http.expectOne(`${apiUrl}/level-3`);
    expect(request.request.method).toBe('DELETE');
    request.flush(null);
  });
});

describe('Admin fine-tuning management', () => {
  let service: Admin;
  let http: HttpTestingController;
  const adminUrl = `${environment.apiUrl}/api/admin`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(Admin);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('starts a versioned ViT fine-tuning cycle with the configured threshold', () => {
    service.startFineTuning(75, 'vit-v1.2.0').subscribe();

    const request = http.expectOne(`${adminUrl}/models/fine-tuning`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ minTrustScore: 75, versionName: 'vit-v1.2.0' });
    request.flush({ id: 'job-1', status: 'pending', progress: 0, minTrustScore: 75 });
  });

  it('polls the detailed job status including logs', () => {
    service.getFineTuningJob('job-1').subscribe((job) => {
      expect(job.progress).toBe(42);
      expect(job.logs?.[0]).toEqual({ level: 'INFO', message: 'Epoch 2/5' });
    });

    const request = http.expectOne(`${adminUrl}/models/fine-tuning/job-1`);
    expect(request.request.method).toBe('GET');
    request.flush({
      id: 'job-1',
      status: 'running',
      progress: 42,
      minTrustScore: 75,
      logs: [{ level: 'INFO', message: 'Epoch 2/5' }],
    });
  });

  it('activates an earlier ViT version for rollback', () => {
    service.activateVit('model-1').subscribe();

    const request = http.expectOne(`${adminUrl}/models/vit/model-1/activate`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({});
    request.flush({ id: 'model-1', name: 'vit-v1.1.0', status: 'active', accuracy: .9, f1Score: .89 });
  });
});
