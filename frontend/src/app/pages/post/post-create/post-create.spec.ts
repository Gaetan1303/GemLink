import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter, Router } from '@angular/router';
import { signal, computed } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { PostCreate } from './post-create';
import { AuthService, User } from '../../../core/services/auth';
import { PostService, Publication } from '../../../core/services/post';

function makeFile(name: string, type: string, sizeBytes = 1024): File {
  const blob = new Blob([new Uint8Array(sizeBytes)], { type });
  return new File([blob], name, { type });
}

describe('PostCreate — US 2.1 Publication d\'un post MVP', () => {
  let component: PostCreate;
  let fixture:   ComponentFixture<PostCreate>;
  let router:    Router;

  let currentUser: ReturnType<typeof signal<User | null | undefined>>;
  let postServiceMock: {
    createPost: ReturnType<typeof vi.fn>;
    deletePost: ReturnType<typeof vi.fn>;
    validateMediaFile: ReturnType<typeof vi.fn>;
  };

  const AUTHENTICATED_USER: User = { id: 1, username: 'gemuser', role: 'ROLE_USER' };

  beforeEach(async () => {
    currentUser = signal<User | null | undefined>(AUTHENTICATED_USER);

    const authServiceMock = {
      currentUser,
      isAuthenticated: computed(() => !!currentUser()),
    };

    postServiceMock = {
      createPost: vi.fn(),
      deletePost: vi.fn(),
      validateMediaFile: vi.fn().mockReturnValue(null),
    };

    await TestBed.configureTestingModule({
      imports: [PostCreate],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: 'auth/login', children: [] }]),
        { provide: AuthService, useValue: authServiceMock },
        { provide: PostService, useValue: postServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PostCreate);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
  });

  it('should create', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('redirige vers /auth/login si l\'utilisateur n\'est pas authentifié', () => {
    currentUser.set(null);
    const navigateSpy = vi.spyOn(router, 'navigate');

    fixture = TestBed.createComponent(PostCreate);
    fixture.detectChanges();

    expect(navigateSpy).toHaveBeenCalledWith(['/auth/login']);
  });

  it('CA-1 : le formulaire ne peut pas être soumis sans fichier média', () => {
    fixture.detectChanges();
    expect(component['canSubmit']()).toBe(false);
  });

  it('CA-2 : un fichier invalide affiche une erreur et n\'est pas retenu', () => {
    fixture.detectChanges();
    postServiceMock.validateMediaFile.mockReturnValue('Formats acceptés : JPEG, PNG, WebP...');

    const file = makeFile('pierre.txt', 'text/plain');
    const input = document.createElement('input');
    Object.defineProperty(input, 'files', { value: [file] });
    component.onFileSelected({ target: input } as unknown as Event);

    expect(component['fileError']()).toContain('Formats acceptés');
    expect(component['selectedFile']()).toBeNull();
  });

  it('CA-1/CA-3 : soumet le post et affiche la confirmation en cas de succès', () => {
    fixture.detectChanges();

    const file = makeFile('pierre.jpg', 'image/jpeg');
    const input = document.createElement('input');
    Object.defineProperty(input, 'files', { value: [file] });
    component.onFileSelected({ target: input } as unknown as Event);

    const createdPublication: Publication = { // création d'un DTO de publication simulé pour le test
  id: 'post-uuid',
  author: {
    id: '1',
    username: 'gemuser',
    avatarUrl: null,
  },
  title: 'Améthyste',
  description: null,
  mediaUrl: 'https://media.gem-link.org/x.jpg',
  mediaType: 'IMAGE',
  status: 'PENDING_ANALYSIS',
  viewCount: 0,
  tags: [],
  createdAt: new Date().toISOString(),
};
    postServiceMock.createPost.mockReturnValue(of(createdPublication));

    component.submit();

    expect(postServiceMock.createPost).toHaveBeenCalledWith(file, '', '', []);
    expect(component['createdPost']()).toEqual(createdPublication);
    expect(component['isSubmitting']()).toBe(false);
  });

  it('affiche un message d\'erreur si la création échoue', () => {
    fixture.detectChanges();

    const file = makeFile('pierre.jpg', 'image/jpeg');
    const input = document.createElement('input');
    Object.defineProperty(input, 'files', { value: [file] });
    component.onFileSelected({ target: input } as unknown as Event);

    postServiceMock.createPost.mockReturnValue(
      throwError(() => ({ error: { message: 'Type de fichier non supporté.' } }))
    );

    component.submit();

    expect(component['submitError']()).toBe('Type de fichier non supporté.');
    expect(component['isSubmitting']()).toBe(false);
  });

  it('CA-1 : refuse la soumission sans fichier et affiche un message', () => {
    fixture.detectChanges();

    component.submit();

    expect(postServiceMock.createPost).not.toHaveBeenCalled();
    expect(component['fileError']()).toContain('photo ou une courte vidéo');
  });
});