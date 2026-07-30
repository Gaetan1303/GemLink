import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { CommentSection } from './comment-section';
import { AuthService, User } from '../../core/services/auth';
import { Comment, CommentPage, CommentService } from '../../core/services/comment';

function makeComment(overrides: Partial<Comment> = {}): Comment {
  return {
    id: 'comment-1',
    publicationId: 'post-1',
    author: { id: '1', username: 'gemuser', avatarUrl: null },
    content: 'Superbe pièce !',
    createdAt: new Date().toISOString(),
    updatedAt: null,
    ...overrides,
  };
}

describe('CommentSection — US 2.4 Commentaires MVP', () => {
  let component: CommentSection;
  let fixture:   ComponentFixture<CommentSection>;

  let currentUser: ReturnType<typeof signal<User | null | undefined>>;
  let commentServiceMock: {
    listComments: ReturnType<typeof vi.fn>;
    createComment: ReturnType<typeof vi.fn>;
    deleteComment: ReturnType<typeof vi.fn>;
    validateContent: ReturnType<typeof vi.fn>;
  };

  function configure(userValue: User | null | undefined, firstPage: CommentPage): void {
    currentUser = signal<User | null | undefined>(userValue);

    commentServiceMock = {
      listComments: vi.fn().mockReturnValue(of(firstPage)),
      createComment: vi.fn(),
      deleteComment: vi.fn(),
      validateContent: vi.fn().mockReturnValue(null),
    };

    TestBed.configureTestingModule({
      imports: [CommentSection],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: AuthService,
          useValue: { currentUser, isAuthenticated: () => !!currentUser() },
        },
        { provide: CommentService, useValue: commentServiceMock },
      ],
    });

    fixture = TestBed.createComponent(CommentSection);
    component = fixture.componentInstance;
    component.postId = 'post-1';
  }

  // ── CA-3 : chargement / pagination ────────────────────────────

  it('CA-3 : charge la première page au montage', () => {
    const comment = makeComment();
    configure(undefined, { items: [comment], nextCursor: null, limit: 20 });

    fixture.detectChanges();

    expect(commentServiceMock.listComments).toHaveBeenCalledWith('post-1');
    expect(component['comments']()).toEqual([comment]);
    expect(component['isLoading']()).toBe(false);
  });

  it('CA-3 : loadMore concatène la page suivante et met à jour le curseur', () => {
    const first = makeComment({ id: 'comment-1' });
    const second = makeComment({ id: 'comment-2' });

    configure(undefined, { items: [first], nextCursor: 'comment-1', limit: 20 });
    fixture.detectChanges();

    commentServiceMock.listComments.mockReturnValue(of({ items: [second], nextCursor: null, limit: 20 }));

    component.loadMore();

    expect(commentServiceMock.listComments).toHaveBeenCalledWith('post-1', 'comment-1');
    expect(component['comments']()).toEqual([first, second]);
    expect(component['nextCursor']()).toBeNull();
  });

  it('n\'appelle pas loadMore quand il n\'y a plus de curseur', () => {
    configure(undefined, { items: [], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    component.loadMore();

    expect(commentServiceMock.listComments).toHaveBeenCalledTimes(1);
  });

  // ── CA-1 : création ──────────────────────────────────────────

  it('CA-1 : submit ajoute le nouveau commentaire en fin de liste', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' }, { items: [], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    const created = makeComment({ id: 'comment-new', content: 'Nouveau commentaire' });
    commentServiceMock.createComment.mockReturnValue(of(created));

    component['newContent'].set('Nouveau commentaire');
    component.submit();

    expect(commentServiceMock.createComment).toHaveBeenCalledWith('post-1', 'Nouveau commentaire');
    expect(component['comments']()).toEqual([created]);
    expect(component['newContent']()).toBe('');
  });

  it('CA-1 : une erreur serveur à la création affiche un message et ne vide pas le champ', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' }, { items: [], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    commentServiceMock.createComment.mockReturnValue(throwError(() => ({ error: { message: 'Erreur serveur.' } })));

    component['newContent'].set('Contenu');
    component.submit();

    expect(component['submitError']()).toBe('Erreur serveur.');
    expect(component['comments']()).toEqual([]);
  });

  // ── CA-2 : suppression / autorisations ────────────────────────

  it('CA-2 : canDeleteComment autorise l\'auteur du commentaire', () => {
    const comment = makeComment({ author: { id: '1', username: 'gemuser', avatarUrl: null } });
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' }, { items: [comment], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    expect(component.canDeleteComment(comment)).toBe(true);
  });

  it('CA-2 : canDeleteComment refuse un utilisateur tiers non privilégié', () => {
    const comment = makeComment({ author: { id: '1', username: 'gemuser', avatarUrl: null } });
    configure({ id: '2', username: 'stranger', role: 'ROLE_USER' }, { items: [comment], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    expect(component.canDeleteComment(comment)).toBe(false);
  });

  it('CA-2 : canDeleteComment autorise un administrateur', () => {
    const comment = makeComment({ author: { id: '1', username: 'gemuser', avatarUrl: null } });
    configure({ id: '99', username: 'admin', role: 'ROLE_ADMIN' }, { items: [comment], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    expect(component.canDeleteComment(comment)).toBe(true);
  });

  it('CA-2 : deleteComment retire le commentaire de la liste en cas de succès', () => {
    const comment = makeComment();
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' }, { items: [comment], nextCursor: null, limit: 20 });
    fixture.detectChanges();

    commentServiceMock.deleteComment.mockReturnValue(of(undefined));

    component.deleteComment(comment.id);

    expect(commentServiceMock.deleteComment).toHaveBeenCalledWith(comment.id);
    expect(component['comments']()).toEqual([]);
  });
});
