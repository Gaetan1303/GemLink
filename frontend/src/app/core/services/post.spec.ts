import { TestBed } from '@angular/core/testing';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';

import { PostService, MAX_IMAGE_SIZE_BYTES, MAX_VIDEO_SIZE_BYTES } from './post';
import { environment } from '../../../environments/environment';

const PUBLICATIONS_URL = `${environment.apiUrl}/api/publications`;

function makeFile(name: string, type: string, sizeBytes: number): File {
  const blob = new Blob([new Uint8Array(sizeBytes)], { type });
  return new File([blob], name, { type });
}

describe('PostService — US 2.1 Publication d\'un post MVP', () => {
  let service:     PostService;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        PostService,
      ],
    });

    service = TestBed.inject(PostService);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpTesting.verify();
  });

  describe('createPost', () => {
    it('CA-1 : envoie le fichier média et les champs optionnels en multipart/form-data', () => {
      const file = makeFile('pierre.jpg', 'image/jpeg', 1024);

      service.createPost(file, 'Améthyste', 'Trouvée en Bretagne', ['violet', 'quartz']).subscribe();

      const req = httpTesting.expectOne(PUBLICATIONS_URL);
      expect(req.request.method).toBe('POST');

      const body = req.request.body as FormData;
      expect(body.get('media')).toBe(file);
      expect(body.get('title')).toBe('Améthyste');
      expect(body.get('description')).toBe('Trouvée en Bretagne');
      expect(body.get('tags')).toBe('violet,quartz');

      req.flush({ id: 'fake-id' });
    });

    it('n\'ajoute pas les champs optionnels vides au FormData', () => {
      const file = makeFile('pierre.jpg', 'image/jpeg', 1024);

      service.createPost(file, '', '', []).subscribe();

      const req = httpTesting.expectOne(PUBLICATIONS_URL);
      const body = req.request.body as FormData;

      expect(body.get('title')).toBeNull();
      expect(body.get('description')).toBeNull();
      expect(body.get('tags')).toBeNull();

      req.flush({ id: 'fake-id' });
    });
  });

  describe('deletePost', () => {
    it('CA-4 : envoie une requête DELETE vers /api/publications/{id}', () => {
      service.deletePost('post-uuid-123').subscribe();

      const req = httpTesting.expectOne(`${PUBLICATIONS_URL}/post-uuid-123`);
      expect(req.request.method).toBe('DELETE');

      req.flush(null);
    });
  });

  describe('listPosts', () => {
    it('US 2.2 : envoie une requête GET paginée', () => {
      service.listPosts(2, 10).subscribe();

      const req = httpTesting.expectOne(r =>
        r.url === PUBLICATIONS_URL && r.params.get('page') === '2' && r.params.get('limit') === '10'
      );
      expect(req.request.method).toBe('GET');

      req.flush({ items: [], page: 2, limit: 10, total: 0, totalPages: 0 });
    });
  });

  describe('getPost', () => {
    it('US 2.2 : envoie une requête GET vers /api/publications/{id}', () => {
      service.getPost('post-uuid-123').subscribe();

      const req = httpTesting.expectOne(`${PUBLICATIONS_URL}/post-uuid-123`);
      expect(req.request.method).toBe('GET');

      req.flush({ id: 'post-uuid-123' });
    });
  });

  describe('validateMediaFile', () => {
    it('CA-2 : accepte une image jpeg valide', () => {
      const file = makeFile('pierre.jpg', 'image/jpeg', 1024);
      expect(service.validateMediaFile(file)).toBeNull();
    });

    it('CA-2 : accepte une vidéo mp4 valide', () => {
      const file = makeFile('pierre.mp4', 'video/mp4', 1024);
      expect(service.validateMediaFile(file)).toBeNull();
    });

    it('CA-2 : rejette un type de fichier non supporté', () => {
      const file = makeFile('pierre.txt', 'text/plain', 10);
      expect(service.validateMediaFile(file)).toContain('Formats acceptés');
    });

    it('CA-2 : rejette une image dépassant 10 Mo', () => {
      const file = makeFile('pierre.jpg', 'image/jpeg', MAX_IMAGE_SIZE_BYTES + 1);
      expect(service.validateMediaFile(file)).toContain('10 Mo');
    });

    it('CA-2 : rejette une vidéo dépassant 100 Mo', () => {
      const file = makeFile('pierre.mp4', 'video/mp4', MAX_VIDEO_SIZE_BYTES + 1);
      expect(service.validateMediaFile(file)).toContain('100 Mo');
    });
  });
});