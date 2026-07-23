import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { PostList } from './post-list';
import { AuthService, User } from '../../../core/services/auth';
import { PostService, Publication, PublicationPage } from '../../../core/services/post';

function makePublication(id: string, title: string): Publication {
  return {
    id,
    author: { id: '1', username: 'gemuser', avatarUrl: null },
    title,
    description: null,
    mediaUrl: 'https://media.gem-link.org/x.jpg',
    mediaType: 'IMAGE',
    status: 'PENDING_ANALYSIS',
    viewCount: 0,
    tags: [],
    identification: null,
    createdAt: new Date().toISOString(),
  };
}

describe('PostList — US 2.2 Consultation des posts (liste)', () => {
  let component: PostList;
  let fixture:   ComponentFixture<PostList>;
  let postServiceMock: { listPosts: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    const authServiceMock = {
      currentUser: signal<User | null | undefined>(undefined),
    };

    postServiceMock = {
      listPosts: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [PostList],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        provideNoopAnimations(),
        { provide: AuthService, useValue: authServiceMock },
        { provide: PostService, useValue: postServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PostList);
    component = fixture.componentInstance;
  });

  it('charge la première page au démarrage, y compris pour un visiteur anonyme', () => {
    const page: PublicationPage = {
      items: [makePublication('post-1', 'Améthyste')],
      page: 1, limit: 20, total: 1, totalPages: 1,
    };
    postServiceMock.listPosts.mockReturnValue(of(page));

    fixture.detectChanges();

    expect(postServiceMock.listPosts).toHaveBeenCalledWith(1, 20);
    expect(component['posts']()).toHaveLength(1);
    expect(component['posts']()[0].title).toBe('Améthyste');
  });

  it('affiche une erreur si le chargement échoue', () => {
    postServiceMock.listPosts.mockReturnValue(throwError(() => new Error('network error')));

    fixture.detectChanges();

    expect(component['loadError']()).toContain('Impossible de charger');
    expect(component['isLoading']()).toBe(false);
  });

  it('goToNextPage() charge la page suivante si elle existe', () => {
    postServiceMock.listPosts.mockReturnValue(of({
      items: [makePublication('post-1', 'A')], page: 1, limit: 20, total: 40, totalPages: 2,
    }));
    fixture.detectChanges();

    postServiceMock.listPosts.mockReturnValue(of({
      items: [makePublication('post-2', 'B')], page: 2, limit: 20, total: 40, totalPages: 2,
    }));
    component.goToNextPage();

    expect(postServiceMock.listPosts).toHaveBeenCalledWith(2, 20);
    expect(component['page']()).toBe(2);
  });

  it('goToNextPage() ne fait rien sur la dernière page', () => {
    postServiceMock.listPosts.mockReturnValue(of({
      items: [], page: 1, limit: 20, total: 0, totalPages: 1,
    }));
    fixture.detectChanges();

    postServiceMock.listPosts.mockClear();
    component.goToNextPage();

    expect(postServiceMock.listPosts).not.toHaveBeenCalled();
  });
});