import { TestBed } from '@angular/core/testing';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideRouter, Router } from '@angular/router';
import { vi } from 'vitest';

import { AuthService } from './auth';
import { environment } from '../../../environments/environment';

// ── Constantes ───────────────────────────────────────────────

const LOGOUT_URL = `${environment.apiUrl}/auth/logout`;

const JWT_VALIDE = [
  btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' })),
  btoa(JSON.stringify({ id: '1', username: 'gemuser', roles: ['USER'], iat: 1000, exp: 1000 + 900 })),
  'signature',
].map(p => p.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')).join('.');

// ── Suite ────────────────────────────────────────────────────

describe('AuthService — US 1.5 Déconnexion', () => {
  let service:    AuthService;
  let httpTesting: HttpTestingController;
  let router:     Router;

  beforeEach(() => {
    localStorage.clear();

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', component: {} as any }]),
        AuthService,
      ],
    });

    service    = TestBed.inject(AuthService);
    httpTesting = TestBed.inject(HttpTestingController);
    router     = TestBed.inject(Router);

    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  afterEach(() => {
    httpTesting.verify();
    localStorage.clear();
    vi.restoreAllMocks();
  });

  // ── CA-1 : appel API avec withCredentials (cookie httpOnly) ──────────────

  it('CA-1 — devrait envoyer POST /api/auth/logout avec withCredentials', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();

    const req = httpTesting.expectOne(LOGOUT_URL);
    expect(req.request.method).toBe('POST');
    expect(req.request.withCredentials).toBe(true);

    req.flush({ message: 'Déconnexion réussie.' });
  });

  // ── CA-2 : JWT transmis dans Authorization: Bearer ───────────────────────

  it('CA-2 — devrait envoyer le JWT dans Authorization: Bearer', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();

    const req = httpTesting.expectOne(LOGOUT_URL);
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${JWT_VALIDE}`);

    req.flush({ message: 'Déconnexion réussie.' });
  });

  it('CA-2 — devrait envoyer la requête même sans JWT (token absent)', () => {
    // localStorage vide → pas d'Authorization header, mais la requête part quand même
    service.logout();

    const req = httpTesting.expectOne(LOGOUT_URL);
    expect(req.request.headers.has('Authorization')).toBe(false);

    req.flush({ message: 'Déconnexion réussie.' });
  });

  // ── CA-3 : nettoyage local + redirection après succès ───────────────────

  it('CA-3 — devrait supprimer le token du localStorage après succès', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();
    httpTesting.expectOne(LOGOUT_URL).flush({ message: 'Déconnexion réussie.' });

    expect(localStorage.getItem('token')).toBeNull();
  });

  it('CA-3 — devrait réinitialiser currentUser après succès', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();
    httpTesting.expectOne(LOGOUT_URL).flush({ message: 'Déconnexion réussie.' });

    expect(service.currentUser()).toBeNull();
  });

  it('CA-3 — devrait naviguer vers "/" après succès', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();
    httpTesting.expectOne(LOGOUT_URL).flush({ message: 'Déconnexion réussie.' });

    expect(router.navigate).toHaveBeenCalledWith(['/']);
  });

  // ── CA-3 : même comportement en cas d'erreur réseau ─────────────────────

  it("CA-3 — devrait nettoyer la session même si l'API est injoignable", () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();
    httpTesting.expectOne(LOGOUT_URL).error(new ProgressEvent('network error'));

    expect(localStorage.getItem('token')).toBeNull();
    expect(service.currentUser()).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/']);
  });

  it('CA-3 — devrait nettoyer la session même sur une réponse 401', () => {
    localStorage.setItem('token', JWT_VALIDE);

    service.logout();
    httpTesting.expectOne(LOGOUT_URL).flush(
      { message: 'Non autorisé.' },
      { status: 401, statusText: 'Unauthorized' }
    );

    expect(localStorage.getItem('token')).toBeNull();
    expect(service.currentUser()).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/']);
  });

});