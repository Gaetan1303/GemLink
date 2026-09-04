import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export type VitrineStatus = 'DRAFT' | 'PUBLISHED';
export type VitrineItemType = 'post' | 'media';

export interface VitrineItemPublication {
  id:        string;
  title:     string | null;
  mediaUrl:  string;
  mediaType: 'IMAGE' | 'VIDEO';
  status:    string;
}

export interface VitrineItem {
  type:         VitrineItemType;
  id:           string;
  position:     number;
  addedAt:      string;
  publication?: VitrineItemPublication;
  mediaUrl?:    string;
  mediaType?:   'IMAGE' | 'VIDEO';
}

export interface Vitrine {
  id:          string;
  title:       string;
  slug:        string;
  description: string | null;
  status:      VitrineStatus;
  viewCount:   number;
  itemsCount:  number;
  items:       VitrineItem[];
  createdAt:   string;
  updatedAt:   string;
  // US 4.2 - CA-3 : présent une fois le QR code généré (à la création
  // côté back). null tant que la génération n'a pas encore eu lieu.
  qrCodeUrl?:  string | null;
}

export interface OrderedItemRef {
  type: VitrineItemType;
  id:   string;
}

// ── US 4.2 - CA-1 : page publique ─────────────────────────────────────
// Forme volontairement alignée sur VitrineItem/VitrineItemPublication
// ci-dessus (même discriminant 'post'/'media', mêmes noms de champs) pour
// pouvoir réutiliser les mêmes helpers d'affichage (thumbnail, label...)
// entre la vue propriétaire et la page publique.

export interface PublicVitrineCreator {
  username:  string;
  avatarUrl: string | null;
}

export interface PublicAiResult {
  pierre:     string | null;
  confidence: number | null;
}

export interface PublicVitrinePublication {
  id:          string;
  title:       string | null;
  description: string | null;
  mediaUrl:    string;
  mediaType:   'IMAGE' | 'VIDEO';
  aiResults:   PublicAiResult[];
}

export interface PublicVitrineItem {
  type:         VitrineItemType;
  id:           string;
  position:     number;
  publication?: PublicVitrinePublication;
  mediaUrl?:    string;
  mediaType?:   'IMAGE' | 'VIDEO';
}

export interface PublicVitrine {
  id:          string;
  slug:        string;
  title:       string;
  description: string | null;
  viewCount:   number;
  creator:     PublicVitrineCreator;
  items:       PublicVitrineItem[];
}

@Injectable({ providedIn: 'root' })
export class VitrineService {

  readonly #http         = inject(HttpClient);
  readonly #apiUrl       = `${environment.apiUrl}/api/vitrines`;
  // US 4.2 - CA-1 : base URL distincte, non authentifiée côté back
  // (firewall vitrine_public sur ^/api/public/).
  readonly #publicApiUrl = `${environment.apiUrl}/api/public/vitrines`;

  listMine(): Observable<{ items: Vitrine[] }> {
    return this.#http.get<{ items: Vitrine[] }>(this.#apiUrl);
  }

  getVitrine(id: string): Observable<Vitrine> {
    return this.#http.get<Vitrine>(`${this.#apiUrl}/${id}`);
  }

  // US 4.2 - CA-1 : consultation publique par slug, sans authentification.
  getPublicVitrine(slug: string): Observable<PublicVitrine> {
    return this.#http.get<PublicVitrine>(`${this.#publicApiUrl}/${slug}`);
  }

  createVitrine(title: string, description: string): Observable<Vitrine> {
    return this.#http.post<Vitrine>(this.#apiUrl, {
      title,
      description: description.trim() === '' ? null : description.trim(),
    });
  }

  updateVitrine(id: string, title: string, description: string | null): Observable<Vitrine> {
    return this.#http.patch<Vitrine>(`${this.#apiUrl}/${id}`, { title, description });
  }

  deleteVitrine(id: string): Observable<void> {
    return this.#http.delete<void>(`${this.#apiUrl}/${id}`);
  }

  addItem(vitrineId: string, publicationId: string): Observable<VitrineItem> {
    return this.#http.post<VitrineItem>(`${this.#apiUrl}/${vitrineId}/items`, { publicationId });
  }

  removeItem(vitrineId: string, publicationId: string): Observable<void> {
    return this.#http.delete<void>(`${this.#apiUrl}/${vitrineId}/items/${publicationId}`);
  }

  addMedia(vitrineId: string, file: File): Observable<VitrineItem> {
    const formData = new FormData();
    formData.append('media', file);

    return this.#http.post<VitrineItem>(`${this.#apiUrl}/${vitrineId}/media`, formData);
  }

  removeMedia(vitrineId: string, mediaId: string): Observable<void> {
    return this.#http.delete<void>(`${this.#apiUrl}/${vitrineId}/media/${mediaId}`);
  }

  reorderItems(vitrineId: string, orderedItems: OrderedItemRef[]): Observable<Vitrine> {
    return this.#http.patch<Vitrine>(`${this.#apiUrl}/${vitrineId}/items/reorder`, { orderedItems });
  }

  publish(vitrineId: string): Observable<Vitrine> {
    return this.#http.post<Vitrine>(`${this.#apiUrl}/${vitrineId}/publish`, {});
  }

  unpublish(vitrineId: string): Observable<Vitrine> {
    return this.#http.post<Vitrine>(`${this.#apiUrl}/${vitrineId}/unpublish`, {});
  }

  // US 4.2 - CA-3 : URL directe (pas d'appel HTTP ici, GET direct sur le
  // navigateur/l'élément <a download> — le contrôleur back fait une
  // redirection 302 vers le CDN).
  qrCodeDownloadUrl(vitrineId: string): string {
    return `${this.#apiUrl}/${vitrineId}/qr-code`;
  }

  // US 4.2 - CA-1 : URL publique canonique, à afficher/copier depuis la
  // vue propriétaire (doit matcher VitrineQrCodeService::buildPublicUrl()
  // côté back — /vitrines/public/:slug).
  publicUrl(slug: string): string {
    return `${window.location.origin}/vitrines/public/${slug}`;
  }
}
