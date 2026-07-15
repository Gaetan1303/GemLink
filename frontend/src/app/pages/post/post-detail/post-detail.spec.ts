import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { ActivatedRoute, Router, provideRouter, convertToParamMap } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { PostDetail } from './post-detail';
import { AuthService, User } from '../../../core/services/auth';
import { PostService, Publication } from '../../../core/services/post';

function makePublication(overrides: Partial<Publication> = {}): Publication {
  return {
    id: 'post-1',
    author: { id: '1', username: 'gemuser', avatarUrl: null },
    title: 'Améthyste',
    description: 'Trouvée en Bretagne',
    mediaUrl: 'https://media.gem-link.org/x.jpg',
    mediaType: 'IMAGE',
    status: 'PENDING_ANALYSIS',
    viewCount: 3,
    tags: ['violet'],
    identification: null,
    createdAt: new Date().toISOString(),
    ...overrides,
  };
}

describe('PostDetail — US 2.2 Consultation des posts (détail)', () => {
  let component: PostDetail;
  let fixture:   ComponentFixture<PostDetail>;
  let router:    Router;

  let currentUser: ReturnType<typeof signal<User | null | undefined>>;
  let postServiceMock: {
    getPost: ReturnType<typeof vi.fn>;
    deletePost: ReturnType<typeof vi.fn>;
  };

  function configure(userValue: User | null | undefined, postId = 'post-1'): void {
    currentUser = signal<User | null | undefined>(userValue);

    TestBed.configureTestingModule({
      imports: [PostDetail],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser } },
        { provide: PostService, useValue: postServiceMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ id: postId }) } },
        },
      ],
    });

    fixture = TestBed.createComponent(PostDetail);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
  }

  beforeEach(() => {
    postServiceMock = {
      getPost: vi.fn(),
      deletePost: vi.fn(),
    };
  });

  it('charge le post correspondant à l\'id de la route, accessible sans authentification', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication()));
    configure(undefined);

    fixture.detectChanges();

    expect(postServiceMock.getPost).toHaveBeenCalledWith('post-1');
    expect(component['post']()?.title).toBe('Améthyste');
    expect(component['isLoading']()).toBe(false);
  });

  it('affiche un message dédié en cas de 404', () => {
    postServiceMock.getPost.mockReturnValue(throwError(() => ({ status: 404 })));
    configure(undefined);

    fixture.detectChanges();

    expect(component['loadError']()).toContain('n\'existe pas');
  });

  it('CA-4 : l\'auteur du post peut le supprimer', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication({ author: { id: '42', username: 'gemuser', avatarUrl: null } })));
    configure({ id: 42, username: 'gemuser', role: 'ROLE_USER' });

    fixture.detectChanges();

    expect(component['canDelete']()).toBe(true);
  });

  it('CA-4 : un utilisateur non lié au post ne peut pas le supprimer', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication({ author: { id: '42', username: 'gemuser', avatarUrl: null } })));
    configure({ id: 99, username: 'autre', role: 'ROLE_USER' });

    fixture.detectChanges();

    expect(component['canDelete']()).toBe(false);
  });

  it('CA-4 : un administrateur peut supprimer le post d\'un tiers', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication({ author: { id: '42', username: 'gemuser', avatarUrl: null } })));
    configure({ id: 1, username: 'admin', role: 'ROLE_ADMIN' });

    fixture.detectChanges();

    expect(component['canDelete']()).toBe(true);
  });

  it('deletePost() redirige vers /posts après suppression réussie', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication({ author: { id: '1', username: 'gemuser', avatarUrl: null } })));
    postServiceMock.deletePost.mockReturnValue(of(undefined));
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });

    fixture.detectChanges();
    const navigateSpy = vi.spyOn(router, 'navigate');

    component.deletePost();

    expect(postServiceMock.deletePost).toHaveBeenCalledWith('post-1');
    expect(navigateSpy).toHaveBeenCalledWith(['/posts']);
  });

  it('affiche une erreur si la suppression échoue', () => {
    postServiceMock.getPost.mockReturnValue(of(makePublication({ author: { id: '1', username: 'gemuser', avatarUrl: null } })));
    postServiceMock.deletePost.mockReturnValue(
      throwError(() => ({ error: { message: 'Non autorisé.' } }))
    );
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });

    fixture.detectChanges();
    component.deletePost();

    expect(component['deleteError']()).toBe('Non autorisé.');
    expect(component['isDeleting']()).toBe(false);
  });
});

describe('PostDetail — US 3.1 Affichage du résultat d\'identification IA', () => {
  let fixture: ComponentFixture<PostDetail>;
  let postServiceMock: { getPost: ReturnType<typeof vi.fn>; deletePost: ReturnType<typeof vi.fn> };

  function configure(post: Publication): void {
    postServiceMock = {
      getPost: vi.fn().mockReturnValue(of(post)),
      deletePost: vi.fn(),
    };

    TestBed.configureTestingModule({
      imports: [PostDetail],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser: signal<User | null | undefined>(undefined) } },
        { provide: PostService, useValue: postServiceMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ id: post.id }) } },
        },
      ],
    });

    fixture = TestBed.createComponent(PostDetail);
    fixture.detectChanges();
  }

  it('affiche la fiche minéralogique quand le post est ANALYZED et qu\'un match existe', () => {
    configure(makePublication({
      status: 'ANALYZED',
      identification: {
        nom: 'Améthyste',
        categorie: 'Silicate (variété de quartz)',
        durete: 7,
        systemeCristallin: 'Trigonal',
        composition: 'SiO2',
        description: 'Variété violette de quartz.',
        confidence: 0.92,
        isHighConfidence: true,
      },
    }));

    const card = fixture.nativeElement.querySelector('.identification-card');
    expect(card).not.toBeNull();
    expect(card.querySelector('h2')?.textContent).toContain('Améthyste');
    expect(card.textContent).toContain('Trigonal');
    expect(card.textContent).toContain('SiO2');
  });

  it('n\'affiche pas d\'avertissement de confiance quand isHighConfidence est true', () => {
    configure(makePublication({
      status: 'ANALYZED',
      identification: {
        nom: 'Améthyste',
        categorie: null,
        durete: null,
        systemeCristallin: null,
        composition: null,
        description: null,
        confidence: 0.92,
        isHighConfidence: true,
      },
    }));

    expect(fixture.nativeElement.querySelector('.identification-warning')).toBeNull();
  });

  it('affiche un avertissement de confiance modérée quand isHighConfidence est false', () => {
    configure(makePublication({
      status: 'ANALYZED',
      identification: {
        nom: 'Andésite',
        categorie: null,
        durete: null,
        systemeCristallin: null,
        composition: null,
        description: null,
        confidence: 0.42,
        isHighConfidence: false,
      },
    }));

    const warning = fixture.nativeElement.querySelector('.identification-warning');
    expect(warning).not.toBeNull();
    expect(warning.textContent).toContain('confiance modérée');
  });

  it('n\'affiche pas la fiche quand identification est null, même si le post est ANALYZED', () => {
    configure(makePublication({ status: 'ANALYZED', identification: null }));

    expect(fixture.nativeElement.querySelector('.identification-card')).toBeNull();
  });

  it('n\'affiche pas la fiche tant que le post est PENDING_ANALYSIS', () => {
    configure(makePublication({
      status: 'PENDING_ANALYSIS',
      identification: null,
    }));

    expect(fixture.nativeElement.querySelector('.identification-card')).toBeNull();
  });

  it('n\'affiche pas de propriétés physiques/optiques omises (null) dans la fiche', () => {
    configure(makePublication({
      status: 'ANALYZED',
      identification: {
        nom: 'Quartz',
        categorie: null,
        durete: null,
        systemeCristallin: 'Trigonal',
        composition: null,
        description: null,
        confidence: 0.8,
        isHighConfidence: true,
      },
    }));

    const properties = fixture.nativeElement.querySelector('.identification-properties');
    // Seul "systemeCristallin" est renseigné : les autres <dt>/<dd> ne
    // doivent pas apparaître (le template les masque via @if individuels).
    expect(properties.textContent).toContain('Trigonal');
    expect(properties.textContent).not.toContain('Catégorie');
    expect(properties.textContent).not.toContain('Composition');
  });
});