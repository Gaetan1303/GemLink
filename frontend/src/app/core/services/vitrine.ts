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
}

export interface OrderedItemRef {
  type: VitrineItemType;
  id:   string;
}

@Injectable({ providedIn: 'root' })
export class VitrineService {

  readonly #http   = inject(HttpClient);
  readonly #apiUrl = `${environment.apiUrl}/api/vitrines`;

  listMine(): Observable<{ items: Vitrine[] }> {
    return this.#http.get<{ items: Vitrine[] }>(this.#apiUrl);
  }

  getVitrine(id: string): Observable<Vitrine> {
    return this.#http.get<Vitrine>(`${this.#apiUrl}/${id}`);
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
}