import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PostAuthor } from './post';

// ── US 2.4 — Commentaires MVP ────────────────────────────────────

export interface Comment {
  id:            string;
  publicationId: string;
  author:        PostAuthor;
  content:       string;
  createdAt:     string;
  updatedAt:     string | null;
}

// CA-3 : pagination cursor-based — nextCursor est l'id du dernier commentaire
// de la page courante, à repasser en paramètre `cursor` pour la page
// suivante ; `null` quand il n'y a plus rien à charger.
export interface CommentPage {
  items:      Comment[];
  nextCursor: string | null;
  limit:      number;
}

// CA-1 : même limite que CommentService::CONTENT_MAX_LENGTH côté backend.
export const COMMENT_MAX_LENGTH = 1000;

@Injectable({ providedIn: 'root' })
export class CommentService {

  readonly #http = inject(HttpClient);
  readonly #apiUrl = environment.apiUrl;

  /**
   * CA-3 : commentaires actifs d'un post, ordre chronologique croissant,
   * accessible aux visiteurs non authentifiés (comme la lecture d'un post).
   */
  listComments(postId: string, cursor: string | null = null, limit = 20): Observable<CommentPage> {
    const params: Record<string, string | number> = { limit };

    if (cursor) {
      params['cursor'] = cursor;
    }

    return this.#http.get<CommentPage>(`${this.#apiUrl}/api/publications/${postId}/comments`, { params });
  }

  /**
   * CA-1 : création réservée à un utilisateur authentifié.
   * CA-4 : déclenche côté serveur la notification in-app de l'auteur du post.
   */
  createComment(postId: string, content: string): Observable<Comment> {
    return this.#http.post<Comment>(`${this.#apiUrl}/api/publications/${postId}/comments`, { content });
  }

  /**
   * CA-2 : suppression réservée à l'auteur, un modérateur ou un administrateur.
   * L'autorisation finale est toujours vérifiée côté serveur.
   */
  deleteComment(commentId: string): Observable<void> {
    return this.#http.delete<void>(`${this.#apiUrl}/api/comments/${commentId}`);
  }

  /**
   * CA-1 : pré-validation côté client (confort UX, la source de vérité
   * reste CommentService::createComment côté serveur).
   */
  validateContent(content: string): string | null {
    const trimmed = content.trim();

    if (trimmed === '') {
      return 'Le commentaire ne peut pas être vide.';
    }

    if (trimmed.length > COMMENT_MAX_LENGTH) {
      return `Un commentaire ne peut pas dépasser ${COMMENT_MAX_LENGTH} caractères.`;
    }

    return null;
  }
}
