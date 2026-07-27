import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { VitrinePublic } from './vitrine-public';
import { PublicVitrine, PublicVitrineItem, VitrineService } from '../../../core/services/vitrine';
import { AuthService } from '../../../core/services/auth';

describe('VitrinePublic', () => {
  let component: VitrinePublic;
  let fixture: ComponentFixture<VitrinePublic>;
  let vitrineServiceMock: { getPublicVitrine: ReturnType<typeof vi.fn> };
  let authServiceMock: { currentUser: ReturnType<typeof vi.fn> };
  let paramMapGet: ReturnType<typeof vi.fn>;

  const buildVitrine = (overrides: Partial<PublicVitrine> = {}): PublicVitrine => ({
    id: '0198abcd-1234-7000-8000-000000000099',
    slug: 'collection-ametystes',
    title: 'Ma collection d\'améthystes',
    description: 'Une sélection de mes plus belles pièces.',
    viewCount: 42,
    creator: { username: 'gaetan_geo', avatarUrl: null },
    items: [],
    ...overrides,
  });

  beforeEach(() => {
    vitrineServiceMock = { getPublicVitrine: vi.fn() };
    authServiceMock = { currentUser: vi.fn().mockReturnValue(null) };
    paramMapGet = vi.fn().mockReturnValue('collection-ametystes');

    TestBed.configureTestingModule({
      imports: [VitrinePublic],
      providers: [
        { provide: VitrineService, useValue: vitrineServiceMock },
        { provide: AuthService, useValue: authServiceMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: { get: paramMapGet } } },
        },
      ],
    });

    fixture = TestBed.createComponent(VitrinePublic);
    component = fixture.componentInstance;
  });

  it('affiche une erreur et n\'appelle pas le service si le slug est absent de l\'URL', () => {
    paramMapGet.mockReturnValue(null);

    fixture.detectChanges();

    expect(component['loadError']()).toBe('Vitrine introuvable.');
    expect(component['isLoading']()).toBe(false);
    expect(vitrineServiceMock.getPublicVitrine).not.toHaveBeenCalled();
  });

  it('charge la Vitrine publique via son slug au démarrage', () => {
    const vitrine = buildVitrine();
    vitrineServiceMock.getPublicVitrine.mockReturnValue(of(vitrine));

    fixture.detectChanges();

    expect(vitrineServiceMock.getPublicVitrine).toHaveBeenCalledWith('collection-ametystes');
    expect(component['vitrine']()).toEqual(vitrine);
    expect(component['isLoading']()).toBe(false);
    expect(component['loadError']()).toBeNull();
  });

  it('affiche un message dédié quand la Vitrine n\'existe pas ou n\'est plus publiée (404)', () => {
    vitrineServiceMock.getPublicVitrine.mockReturnValue(throwError(() => ({ status: 404 })));

    fixture.detectChanges();

    expect(component['loadError']()).toBe('Cette Vitrine n\'existe pas ou n\'est plus publiée.');
    expect(component['isLoading']()).toBe(false);
  });

  it('affiche un message générique pour les autres erreurs (ex: 500)', () => {
    vitrineServiceMock.getPublicVitrine.mockReturnValue(throwError(() => ({ status: 500 })));

    fixture.detectChanges();

    expect(component['loadError']()).toBe('Impossible de charger cette Vitrine pour le moment.');
    expect(component['isLoading']()).toBe(false);
  });

  describe('currentRole', () => {
    it('retourne VISITEUR si personne n\'est connecté', () => {
      authServiceMock.currentUser.mockReturnValue(null);

      expect(component['currentRole']()).toBe('VISITEUR');
    });

    it('retourne le rôle de l\'utilisateur connecté', () => {
      authServiceMock.currentUser.mockReturnValue({ role: 'USER' });

      expect(component['currentRole']()).toBe('USER');
    });
  });

  describe('helpers d\'affichage des items', () => {
    const postItem: PublicVitrineItem = {
      type: 'post',
      id: 'pub-1',
      position: 0,
      publication: {
        id: 'pub-1',
        title: 'Améthyste brute',
        description: null,
        mediaUrl: 'https://cdn.gem-link.org/pub-1.jpg',
        mediaType: 'IMAGE',
        aiResults: [],
      },
    };

    const postItemNoTitle: PublicVitrineItem = {
      type: 'post',
      id: 'pub-2',
      position: 1,
      publication: {
        id: 'pub-2',
        title: null,
        description: null,
        mediaUrl: 'https://cdn.gem-link.org/pub-2.jpg',
        mediaType: 'VIDEO',
        aiResults: [],
      },
    };

    const mediaItem: PublicVitrineItem = {
      type: 'media',
      id: 'media-1',
      position: 2,
      mediaUrl: 'https://cdn.gem-link.org/media-1.jpg',
      mediaType: 'IMAGE',
    };

    it('itemThumbnailUrl() lit publication.mediaUrl pour un post', () => {
      expect(component['itemThumbnailUrl'](postItem)).toBe('https://cdn.gem-link.org/pub-1.jpg');
    });

    it('itemThumbnailUrl() lit mediaUrl directement pour un media', () => {
      expect(component['itemThumbnailUrl'](mediaItem)).toBe('https://cdn.gem-link.org/media-1.jpg');
    });

    it('itemMediaType() lit publication.mediaType pour un post', () => {
      expect(component['itemMediaType'](postItemNoTitle)).toBe('VIDEO');
    });

    it('itemMediaType() lit mediaType directement pour un media', () => {
      expect(component['itemMediaType'](mediaItem)).toBe('IMAGE');
    });

    it('itemLabel() retourne le titre du post s\'il existe', () => {
      expect(component['itemLabel'](postItem)).toBe('Améthyste brute');
    });

    it('itemLabel() retourne "Sans titre" si le post n\'a pas de titre', () => {
      expect(component['itemLabel'](postItemNoTitle)).toBe('Sans titre');
    });

    it('itemLabel() retourne "Photo/vidéo" pour un media', () => {
      expect(component['itemLabel'](mediaItem)).toBe('Photo/vidéo');
    });
  });
});