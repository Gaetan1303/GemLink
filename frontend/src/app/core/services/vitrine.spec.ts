import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { PublicVitrine, VitrineService } from './vitrine';
import { environment } from '../../../environments/environment';

// US 4.2 — Ne couvre que les méthodes ajoutées pour cette US
// (getPublicVitrine, qrCodeDownloadUrl, publicUrl). Si un vitrine.spec.ts
// couvrant les méthodes de US 4.1 existe déjà, fusionnez ces cas dedans
// plutôt que de garder deux fichiers de test pour le même service.
describe('VitrineService — US 4.2', () => {
  let service: VitrineService;
  let httpMock: HttpTestingController;

  const apiUrl = `${environment.apiUrl}/api/vitrines`;
  const publicApiUrl = `${environment.apiUrl}/api/public/vitrines`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(VitrineService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  describe('getPublicVitrine()', () => {
    it('effectue un GET sur /api/public/vitrines/:slug (sans authentification)', () => {
      const expected: PublicVitrine = {
        id: '0198abcd-1234-7000-8000-000000000099',
        slug: 'collection-ametystes',
        title: 'Ma collection d\'améthystes',
        description: null,
        viewCount: 42,
        creator: { username: 'gaetan_geo', avatarUrl: null },
        items: [],
      };

      service.getPublicVitrine('collection-ametystes').subscribe((result) => {
        expect(result).toEqual(expected);
      });

      const req = httpMock.expectOne(`${publicApiUrl}/collection-ametystes`);
      expect(req.request.method).toBe('GET');
      req.flush(expected);
    });
  });

  describe('qrCodeDownloadUrl()', () => {
    it('construit l\'URL de téléchargement du QR code à partir de l\'id', () => {
      const url = service.qrCodeDownloadUrl('0198abcd-1234-7000-8000-000000000099');

      expect(url).toBe(`${apiUrl}/0198abcd-1234-7000-8000-000000000099/qr-code`);
    });
  });

  describe('publicUrl()', () => {
    it('construit l\'URL publique canonique à partir du slug et de l\'origine courante', () => {
      const url = service.publicUrl('collection-ametystes');

      expect(url).toBe(`${window.location.origin}/vitrines/public/collection-ametystes`);
    });
  });
});