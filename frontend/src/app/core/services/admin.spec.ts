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
