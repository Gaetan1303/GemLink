import { computed, inject, Injectable, signal } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router } from '@angular/router';
import { map, Observable, tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { jwtDecode } from 'jwt-decode';
import { MenuRole } from '../../components/menu-burger/menu-navigation.model';

// ── Interfaces ───────────────────────────────────────────────

export interface RegisterRequest {
  username:     string;
  email:        string;
  passwordHash: string;
}

export interface AuthMessageResponse {
  message: string;
}

export interface LoginPayload {
  email:        string;
  passwordHash: string;
}

export interface LoginResponse {
  token: string;
}

export interface User {
  id:       string;
  username: string;
  role:     MenuRole;
}

// ── Service ──────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class AuthService {

  readonly #http   = inject(HttpClient);
  readonly #router = inject(Router);
  readonly #apiUrl = `${environment.apiUrl}/auth`;

  currentUser     = signal<User | null | undefined>(undefined);
  isAuthenticated = computed(() => !!this.currentUser());

  constructor() {
    const token = this.getUsableAccessToken();
    if (token) {
      this.decodeAndSetUser(token);
    }
  }

  /**
   * Ne retourne jamais un JWT expiré ou illisible. Cela évite que
   * l'intercepteur envoie un ancien Bearer token sur les routes publiques :
   * Symfony tenterait alors de l'authentifier et répondrait 401 avant même
   * d'appliquer la règle PUBLIC_ACCESS.
   */
  getUsableAccessToken(): string | null {
    const token = localStorage.getItem('token');
    if (!token) {
      return null;
    }

    try {
      const decoded = jwtDecode<{ exp?: number }>(token);
      const now = Math.floor(Date.now() / 1000);

      if (typeof decoded.exp !== 'number' || decoded.exp <= now) {
        localStorage.removeItem('token');
        this.currentUser.set(null);
        return null;
      }

      return token;
    } catch {
      localStorage.removeItem('token');
      this.currentUser.set(null);
      return null;
    }
  }

  register(data: RegisterRequest): Observable<void> {
    return this.#http.post<void>(`${this.#apiUrl}/register`, data);
  }

  validateEmail(token: string): Observable<AuthMessageResponse> {
    return this.#http.get<AuthMessageResponse>(`${this.#apiUrl}/validate-email/${token}`);
  }

  resendValidationEmail(email: string): Observable<AuthMessageResponse> {
    return this.#http.post<AuthMessageResponse>(`${this.#apiUrl}/resend-validation-email`, { email });
  }

  // US 1.3
  login(payload: LoginPayload): Observable<LoginResponse> {
    return this.#http.post<LoginResponse>(
      `${this.#apiUrl}/login`,
      payload,
      { withCredentials: true },
    ).pipe(
      tap(response => {
        localStorage.setItem('token', response.token);
        this.decodeAndSetUser(response.token);
      }),
    );
  }

  /** 
   * / US 1.4 — appelé par l'intercepteur
   *
   * CA-1 : withCredentials envoie le cookie httpOnly `refresh_token` automatiquement.
   * CA-2 : le backend révoque l'ancien token et pose un nouveau cookie (rotation).
   * CA-3 : si la réponse est 401, l'intercepteur catchError gère la redirection.
   *
   * Retourne le nouveau JWT (string) pour que l'intercepteur puisse immédiatement
   * rejouer la requête originale sans second aller-retour.
   */
  refresh(): Observable<string> {
    return this.#http.post<LoginResponse>(
      `${this.#apiUrl}/refresh`,
      {},
      { withCredentials: true },
    ).pipe(
      tap(response => {
        localStorage.setItem('token', response.token);
        this.decodeAndSetUser(response.token);
      }),
      map(response => response.token),
    );
  }

  /**
   * US 1.5 : Déconnexion explicite.
   *
   * CA-1 : withCredentials envoie le cookie httpOnly pour que le backend le révoque.
   * CA-2 : envoie le JWT dans Authorization pour que le backend le mette en blocklist.
   * CA-3 : après confirmation (ou erreur réseau), nettoyage local + redirection vers "/".
   */
  logout(): void {
    const token = localStorage.getItem('token');
    const headers = token
      ? new HttpHeaders({ Authorization: `Bearer ${token}` })
      : new HttpHeaders();

    this.#http
      .post<AuthMessageResponse>(
        `${environment.apiUrl}/auth/logout`,
        {},
        { headers, withCredentials: true },
      )
      .subscribe({
        next:  () => this.clearSession(),
        error: () => this.clearSession(),
      });
  }

  // US 1.6 — Étape 1 : demande de réinitialisation
  // CA-1 : l'API renvoie toujours 202 + message générique, côté client on fait pareil.
  requestPasswordReset(email: string): Observable<AuthMessageResponse> {
    return this.#http.post<AuthMessageResponse>(
      `${this.#apiUrl}/reset-password/request`,
      { email },
    );
  }

  // US 1.6 — Étape 2 : confirmation du nouveau mot de passe
  // CA-3 : si 200, toutes les sessions existantes ont été révoquées côté backend.
  resetPassword(token: string, password: string): Observable<AuthMessageResponse> {
    return this.#http.post<AuthMessageResponse>(
      `${this.#apiUrl}/reset-password/confirm`,
      { token, password },
    );
  }

  /**
   * Nettoyage de session côté client.
   * Public car appelé aussi par l'intercepteur auth (US 1.4 CA-3).
   */
  clearSession(): void {
    localStorage.removeItem('token');
    this.currentUser.set(null);
    this.#router.navigate(['/']);
  }

  private decodeAndSetUser(token: string): void {
    try {
      const decoded: {
        roles: MenuRole[];
        username: string;
        iat: number;
        exp: number;
        id: string;
      } = jwtDecode(token);

      this.currentUser.set({
        id:       decoded.id,
        username: decoded.username,
        role: decoded.roles.includes('ROLE_ADMIN')
          ? 'ROLE_ADMIN'
          : decoded.roles.includes('ROLE_MODERATOR')
            ? 'ROLE_MODERATOR'
            : 'ROLE_USER',
      });
    } catch (error) {
      console.error('Failed to decode token:', error);
      this.currentUser.set(null);
    }
  }
}
