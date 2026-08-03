import { TestBed } from '@angular/core/testing';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';

import { CommentService, COMMENT_MAX_LENGTH } from './comment';
import { environment } from '../../../environments/environment';

const POST_ID = 'post-uuid-123';
const COMMENTS_URL = `${environment.apiUrl}/api/publications/${POST_ID}/comments`;

describe('CommentService — US 2.4 Commentaires MVP', () => {
  let service:     CommentService;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        CommentService,
      ],
    });

    service = TestBed.inject(CommentService);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpTesting.verify();
  });

  describe('listComments', () => {
    it('CA-3 : requête GET sans curseur pour la première page', () => {
      service.listComments(POST_ID).subscribe();

      const req = httpTesting.expectOne((r) => r.url === COMMENTS_URL);
      expect(req.request.method).toBe('GET');
      expect(req.request.params.get('limit')).toBe('20');
      expect(req.request.params.has('cursor')).toBe(false);

      req.flush({ items: [], nextCursor: null, limit: 20 });
    });

    it('CA-3 : transmet le curseur pour les pages suivantes', () => {
      service.listComments(POST_ID, 'cursor-uuid', 20).subscribe();

      const req = httpTesting.expectOne((r) => r.url === COMMENTS_URL);
      expect(req.request.params.get('cursor')).toBe('cursor-uuid');

      req.flush({ items: [], nextCursor: null, limit: 20 });
    });
  });

  describe('createComment', () => {
    it('CA-1 : envoie le contenu en POST', () => {
      service.createComment(POST_ID, 'Superbe pièce !').subscribe();

      const req = httpTesting.expectOne(COMMENTS_URL);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({ content: 'Superbe pièce !' });

      req.flush({ id: 'comment-uuid' });
    });
  });

  describe('deleteComment', () => {
    it('CA-2 : envoie une requête DELETE vers /api/comments/{id}', () => {
      service.deleteComment('comment-uuid-456').subscribe();

      const req = httpTesting.expectOne(`${environment.apiUrl}/api/comments/comment-uuid-456`);
      expect(req.request.method).toBe('DELETE');

      req.flush(null);
    });
  });

  describe('validateContent', () => {
    it('CA-1 : rejette un contenu vide ou uniquement composé d\'espaces', () => {
      expect(service.validateContent('')).not.toBeNull();
      expect(service.validateContent('   ')).not.toBeNull();
    });

    it('CA-1 : rejette un contenu de plus de 1000 caractères', () => {
      expect(service.validateContent('a'.repeat(COMMENT_MAX_LENGTH + 1))).not.toBeNull();
    });

    it('CA-1 : accepte un contenu valide', () => {
      expect(service.validateContent('Superbe pièce !')).toBeNull();
      expect(service.validateContent('a'.repeat(COMMENT_MAX_LENGTH))).toBeNull();
    });
  });
});
