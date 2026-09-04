import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

// ── US 2.7 — Validation communautaire de l'identification IA ───

export type ValidationAction = 'CONFIRM' | 'CORRECT' | 'REJECT';

export interface PierreSummary {
  id:  string;
  nom: string;
}

export interface Validation {
  id:                  string;
  action:              ValidationAction;
  pierre:              PierreSummary;
  proposedLabel:       string | null;
  trustScoreSnapshot:  number;
  createdAt:           string;
}

@Injectable({ providedIn: 'root' })
export class ValidationService {

  readonly #http = inject(HttpClient);
  readonly #apiUrl = `${environment.apiUrl}/api`;

  /**
   * CA-1 : une seule validation par (post, utilisateur) côté serveur —
   * une resoumission remplace silencieusement le choix précédent.
   * proposedLabel n'est pertinent que pour l'action CORRECT.
   */
  submitValidation(postId: string, action: ValidationAction, proposedLabel?: string): Observable<Validation> {
    return this.#http.post<Validation>(`${this.#apiUrl}/publications/${postId}/validations`, {
      action,
      proposedLabel: proposedLabel?.trim() || null,
    });
  }

  /**
   * Validation déjà soumise par l'utilisateur courant pour ce post (ou
   * `null`), pour pré-remplir le composant avec son choix précédent.
   */
  getMine(postId: string): Observable<Validation | null> {
    return this.#http.get<Validation | null>(`${this.#apiUrl}/publications/${postId}/validations/mine`);
  }

  /**
   * CA-1 : autocomplétion sur les minéraux connus pour le champ de
   * correction. Moins de 2 caractères : pas d'appel réseau, l'API renvoie
   * de toute façon un tableau vide en dessous de ce seuil.
   */
  searchPierres(query: string): Observable<PierreSummary[]> {
    const trimmed = query.trim();

    if (trimmed.length < 2) {
      return of([]);
    }

    return this.#http.get<PierreSummary[]>(`${this.#apiUrl}/pierres/search`, { params: { q: trimmed } });
  }

  /**
   * Variante prête à brancher sur un FormControl.valueChanges : anti-rebond
   * + annulation des requêtes obsolètes + tolérance aux erreurs réseau
   * ponctuelles (l'autocomplétion n'est qu'un confort, pas une action
   * bloquante — on ne casse pas la saisie sur un blip réseau).
   */
  searchPierresDebounced(input$: Observable<string>): Observable<PierreSummary[]> {
    return input$.pipe(
      debounceTime(250),
      distinctUntilChanged(),
      switchMap((query) => this.searchPierres(query).pipe(catchError(() => of([])))),
    );
  }
}
