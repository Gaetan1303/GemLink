import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { Router, provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { PostCreate } from './post-create';
import { AuthService, User } from '../../../core/services/auth';
import { PostService, Publication } from '../../../core/services/post';

function makePublication(overrides: Partial<Publication> = {}): Publication {
  return {
    id: 'post-1',
    author: { id: '1', username: 'gemuser', avatarUrl: null },
    title: 'Améthyste',
    description: null,
    mediaUrl: 'https://media.gem-link.org/x.jpg',
    mediaType: 'IMAGE',
    status: 'PENDING_ANALYSIS',
    viewCount: 0,
    tags: [],
    identification: null,
    createdAt: new Date().toISOString(),
    ...overrides,
  };
}

// US 2.1 CA-1/CA-2 — Publication d'un post MVP.
describe('PostCreate', () => {
  let component: PostCreate;
  let fixture:   ComponentFixture<PostCreate>;
  let router:    Router;

  let currentUser: ReturnType<typeof signal<User | null | undefined>>;
  let postServiceMock: {
    createPost: ReturnType<typeof vi.fn>;
    validateMediaFile: ReturnType<typeof vi.fn>;
  };

  function configure(userValue: User | null | undefined): void {
    currentUser = signal<User | null | undefined>(userValue);

    postServiceMock = {
      createPost: vi.fn(),
      // Comportement réel par défaut (pas de mock naïf qui accepterait
      // n'importe quoi) : un fichier vide/inconnu est rejeté.
      validateMediaFile: vi.fn().mockReturnValue(null),
    };

    TestBed.configureTestingModule({
      imports: [PostCreate],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser, isAuthenticated: () => userValue != null } },
        { provide: PostService, useValue: postServiceMock },
      ],
    });

    fixture = TestBed.createComponent(PostCreate);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
  }

  function makeFile(type = 'image/jpeg'): File {
    return new File(['contenu'], 'pierre.jpg', { type });
  }

  function selectFile(file: File): void {
    const input = { files: [file], value: '' } as unknown as HTMLInputElement;
    component.onFileSelected({ target: input } as unknown as Event);
  }

  it('redirige vers /auth/login si l\'utilisateur n\'est pas authentifié', () => {
    currentUser = signal<User | null | undefined>(null);

    TestBed.configureTestingModule({
      imports: [PostCreate],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser, isAuthenticated: () => false } },
        { provide: PostService, useValue: postServiceMock },
      ],
    });

    // Le spy doit exister AVANT la création du composant : c'est le
    // constructeur de PostCreate qui appelle navigate(), donc poser le spy
    // après TestBed.createComponent() arrive toujours trop tard.
    router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    fixture = TestBed.createComponent(PostCreate);
    component = fixture.componentInstance;

    expect(navigateSpy).toHaveBeenCalledWith(['/auth/login']);
  });

  it('rejette un fichier invalide selon PostService.validateMediaFile', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    postServiceMock.validateMediaFile.mockReturnValue('Formats acceptés : JPEG, PNG, WebP pour une image, MP4 pour une vidéo.');
    fixture.detectChanges();

    selectFile(makeFile('application/pdf'));

    expect(component['selectedFile']()).toBeNull();
    expect(component['fileError']()).toContain('Formats acceptés');
  });

  it('accepte un fichier valide et le mémorise', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    const file = makeFile();
    selectFile(file);

    expect(component['selectedFile']()).toBe(file);
    expect(component['fileError']()).toBeNull();
  });

  it('submit() refuse sans fichier sélectionné', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.submit();

    expect(postServiceMock.createPost).not.toHaveBeenCalled();
    expect(component['fileError']()).toContain('photo ou une courte vidéo');
  });

  it('US 3.1 : submit() réussi redirige vers le détail du post créé (pas un écran statique)', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();
    selectFile(makeFile());

    postServiceMock.createPost.mockReturnValue(of(makePublication({ id: 'post-42' })));
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.submit();

    expect(postServiceMock.createPost).toHaveBeenCalledWith(expect.any(File), '', '', []);
    expect(navigateSpy).toHaveBeenCalledWith(['/posts', 'post-42']);
    expect(component['isSubmitting']()).toBe(false);
  });

  it('submit() affiche une erreur si la création échoue', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();
    selectFile(makeFile());

    postServiceMock.createPost.mockReturnValue(
      throwError(() => ({ error: { message: 'Fichier rejeté par le serveur.' } }))
    );
    const navigateSpy = vi.spyOn(router, 'navigate');
    navigateSpy.mockClear();

    component.submit();

    expect(component['submitError']()).toBe('Fichier rejeté par le serveur.');
    expect(component['isSubmitting']()).toBe(false);
    expect(navigateSpy).not.toHaveBeenCalled();
  });

  it('parse les tags séparés par des virgules et les limite à 10', () => {
    configure({ id: 1, username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();
    selectFile(makeFile());

    const manyTags = Array.from({ length: 15 }, (_, i) => `tag${i}`).join(',');
    component['postForm'].patchValue({ tagsInput: manyTags });

    postServiceMock.createPost.mockReturnValue(of(makePublication()));
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
    component.submit();

    const [, , , tags] = postServiceMock.createPost.mock.calls[0];
    expect(tags).toHaveLength(10);
  });
});
