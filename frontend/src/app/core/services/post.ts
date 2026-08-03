import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, timer } from 'rxjs';
import { distinctUntilChanged, switchMap, takeWhile } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

// ── US 2.1 — Publication d'un post MVP ──────────────────────────

export type MediaType = 'IMAGE' | 'VIDEO';
export type PostStatus = 'PENDING_ANALYSIS' | 'ANALYZED' | 'ANALYSIS_FAILED' | 'COMMUNITY_VALIDATED';

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
  id?:                string;
  nom:               string;
  categorie:         string | null;
  durete:            number | null;
  systemeCristallin: string | null;
  composition:       string | null;
  description:       string | null;
  confidence:        number;
  isHighConfidence:  boolean;
  confidencePercent?: number;
  confidenceThreshold?: number;
  isUncertain?:       boolean;
  communityValidated?: boolean;
  detectorConfidence?: number;
  detections?: PipelineDetection[];
  modelVersion?: PipelineModelVersion;
}

export interface PipelineDetection {
  nom:                string;
  confidence:         number;
  detectorConfidence: number | null;
  bbox:                [number, number, number, number];
}

export interface PipelineModelVersion {
  yolo: string;
  vit:  string;
  clip: string;
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
  likeCount:      number;
  likedByCurrentUser: boolean;
  tags:           string[];
  identification: PublicationIdentification | null;
  createdAt:      string;
}

export interface SimilarPublication extends Publication {
  similarity: number;
}

/** Réponse du toggle `POST /api/publications/{id}/like`. */
export interface LikeToggleResponse {
  liked: boolean;
  likeCount: number;
}

export interface PublicIdentification {
  id: string;
  status: PostStatus;
  result: PublicationIdentification | null;
  expiresAt: string;
}

// US 2.2 — Consultation des posts (liste + détail)
export interface PublicationPage {
  items:       Publication[];
  limit:       number;
  nextCursor:  string | null;
  hasNextPage: boolean;
}

export interface FeedFilters {
  tag?: string;
  mineral?: string;
  minConfidence?: number;
  personalized?: boolean;
}

// CA-2 : mêmes limites que le backend (validation client = confort UX,
// la source de vérité reste MediaValidatorService côté serveur).
export const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4'];
export const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;   // 10 Mo
export const MAX_VIDEO_SIZE_BYTES = 100 * 1024 * 1024;  // 100 Mo
export const MAX_VIDEO_DURATION_SECONDS = 60;

// US 3.1 : intervalle de polling du statut d'analyse. Aligné très en dessous
// du premier délai de retry backend (30 s, cf. CA-3) pour rester réactif côté
// UX sans bombarder l'API pendant les tentatives de retry du worker.
const ANALYSIS_POLL_INTERVAL_MS = 4000;

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
  listPosts(cursor: string | null = null, limit = 20, filters: FeedFilters = {}): Observable<PublicationPage> {
    const params: Record<string, string | number | boolean> = { limit };
    if (cursor) params['cursor'] = cursor;
    if (filters.tag) params['tag'] = filters.tag;
    if (filters.mineral) params['mineral'] = filters.mineral;
    if (filters.minConfidence !== undefined) params['minConfidence'] = filters.minConfidence;
    if (filters.personalized) params['personalized'] = true;
    return this.#http.get<PublicationPage>(this.#apiUrl, { params });
  }

  /**
   * US 2.2 — Détail public d'un post.
   */
  getPost(postId: string): Observable<Publication> {
    return this.#http.get<Publication>(`${this.#apiUrl}/${postId}`);
  }

  getSimilarPosts(postId: string, limit = 5): Observable<{ items: SimilarPublication[] }> {
    return this.#http.get<{ items: SimilarPublication[] }>(`${this.#apiUrl}/${postId}/similar`, {
      params: { limit: Math.min(5, Math.max(1, limit)) },
    });
  }

  /** US 2.3 CA-1 : le serveur ajoute ou retire le like selon son état actuel. */
  toggleLike(postId: string): Observable<LikeToggleResponse> {
    return this.#http.post<LikeToggleResponse>(`${this.#apiUrl}/${postId}/like`, {});
  }

  identifyPublic(file: File): Observable<PublicIdentification> {
    const formData = new FormData();
    formData.append('image', file);
    return this.#http.post<PublicIdentification>(`${environment.apiUrl}/api/public/identifications`, formData);
  }

  getPublicIdentification(id: string): Observable<PublicIdentification> {
    return this.#http.get<PublicIdentification>(`${environment.apiUrl}/api/public/identifications/${id}`);
  }

  /**
   * US 3.1 — Suit le statut d'analyse IA d'un post tant qu'il est
   * PENDING_ANALYSIS, en ré-interrogeant GET /publications/{id} à intervalle
   * régulier (pas de websocket à ce stade du projet). Émet une valeur
   * immédiatement, puis à chaque changement de statut, et se complète dès
   * que le post quitte PENDING_ANALYSIS (ANALYZED ou ANALYSIS_FAILED) — y
   * compris la dernière valeur qui a fait basculer l'état (`inclusive: true`).
   *
   * Le composant appelant reste responsable de se désabonner (ngOnDestroy)
   * si l'utilisateur quitte la page avant la fin de l'analyse.
   */
  pollAnalysis(postId: string, intervalMs = ANALYSIS_POLL_INTERVAL_MS): Observable<Publication> {
    return timer(0, intervalMs).pipe(
      switchMap(() => this.getPost(postId)),
      distinctUntilChanged((previous, current) => previous.status === current.status),
      takeWhile((post) => post.status === 'PENDING_ANALYSIS', true),
    );
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
