import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

// ── US 2.1 — Publication d'un post MVP ──────────────────────────

export type MediaType = 'IMAGE' | 'VIDEO';
export type PostStatus = 'PENDING_ANALYSIS' | 'ANALYZED' | 'ANALYSIS_FAILED';

export interface PostAuthor {
  id:        string;
  username:  string;
  avatarUrl: string | null;
}

// US 3.1 — Résultat de l'identification IA (Pierre + confidence), renvoyé par
// PublicationController::serializeIdentification(). `null` tant que le post
// n'est pas ANALYZED, ou si aucun match n'a été persisté (cas rare mais
// possible si l'analyse a échoué en base sans mettre à jour le statut).
export interface PublicationIdentification {
  nom:               string;
  categorie:         string | null;
  durete:            number | null;
  systemeCristallin: string | null;
  composition:       string | null;
  description:       string | null;
  confidence:        number;
  isHighConfidence:  boolean;
}

export interface Publication {
  id:             string;
  author:         PostAuthor;
  title:          string | null;
  description:    string | null;
  mediaUrl:       string;
  mediaType:      MediaType;
  status:         PostStatus;
  viewCount:      number;
  tags:           string[];
  identification: PublicationIdentification | null;
  createdAt:      string;
}

// US 2.2 — Consultation des posts (liste + détail)
export interface PublicationPage {
  items:      Publication[];
  page:       number;
  limit:      number;
  total:      number;
  totalPages: number;
}

// CA-2 : mêmes limites que le backend (validation client = confort UX,
// la source de vérité reste MediaValidatorService côté serveur).
export const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4'];
export const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;   // 10 Mo
export const MAX_VIDEO_SIZE_BYTES = 100 * 1024 * 1024;  // 100 Mo
export const MAX_VIDEO_DURATION_SECONDS = 60;

@Injectable({ providedIn: 'root' })
export class PostService {

  readonly #http   = inject(HttpClient);
  readonly #apiUrl = `${environment.apiUrl}/api/publications`;

  /**
   * CA-1 : le fichier média est obligatoire ; title/description/tags optionnels.
   * CA-3 : la création est immédiate, l'analyse IA se fait en tâche de fond côté serveur.
   */
  createPost(mediaFile: File, title: string, description: string, tags: string[]): Observable<Publication> {
    const formData = new FormData();
    formData.append('media', mediaFile);

    if (title.trim() !== '') {
      formData.append('title', title.trim());
    }
    if (description.trim() !== '') {
      formData.append('description', description.trim());
    }
    if (tags.length > 0) {
      formData.append('tags', tags.join(','));
    }

    return this.#http.post<Publication>(this.#apiUrl, formData);
  }

  /**
   * CA-4 : la suppression est réservée à l'auteur, un modérateur ou un administrateur.
   * L'autorisation finale est toujours vérifiée côté serveur.
   */
  deletePost(postId: string): Observable<void> {
    return this.#http.delete<void>(`${this.#apiUrl}/${postId}`);
  }

  /**
   * US 2.2 — Feed public paginé (accessible aux visiteurs non authentifiés).
   */
  listPosts(page = 1, limit = 20): Observable<PublicationPage> {
    return this.#http.get<PublicationPage>(this.#apiUrl, { params: { page, limit } });
  }

  /**
   * US 2.2 — Détail public d'un post.
   */
  getPost(postId: string): Observable<Publication> {
    return this.#http.get<Publication>(`${this.#apiUrl}/${postId}`);
  }

  /**
   * CA-2 : pré-validation côté client (type MIME déclaré + taille). Renvoie un
   * message d'erreur en français si le fichier est invalide, sinon `null`.
   */
  validateMediaFile(file: File): string | null {
    const isImage = ALLOWED_IMAGE_MIME_TYPES.includes(file.type);
    const isVideo = ALLOWED_VIDEO_MIME_TYPES.includes(file.type);

    if (!isImage && !isVideo) {
      return 'Formats acceptés : JPEG, PNG, WebP pour une image, MP4 pour une vidéo.';
    }

    if (isImage && file.size > MAX_IMAGE_SIZE_BYTES) {
      return 'L\'image dépasse la taille maximale autorisée de 10 Mo.';
    }

    if (isVideo && file.size > MAX_VIDEO_SIZE_BYTES) {
      return 'La vidéo dépasse la taille maximale autorisée de 100 Mo.';
    }

    return null;
  }
}