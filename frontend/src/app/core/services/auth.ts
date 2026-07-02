import { computed, inject, Injectable, signal } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
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
  id:       number;
  username: string;
  role:     MenuRole;
}

// ── Service ──────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class AuthService {

  readonly #http   = inject(HttpClient);
  readonly #router = inject(Router);
  readonly #apiUrl = `${environment.apiUrl}/auth`;

  // --- State management ---
  currentUser    = signal<User | null | undefined>(undefined);
  isAuthenticated = computed(() => !!this.currentUser());

  constructor() {
    const token = localStorage.getItem('token');
    if (token) {
      this.decodeAndSetUser(token);
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

  // CA-1 US 1.3 : withCredentials obligatoire pour recevoir le cookie refresh token httpOnly
  login(payload: LoginPayload): Observable<LoginResponse> {
    return this.#http.post<LoginResponse>(
      `${this.#apiUrl}/login`,
      payload,
      { withCredentials: true },
    ).pipe(
      tap(response => {
        localStorage.setItem('token', response.token);
        this.decodeAndSetUser(response.token);
      })
    );
  }

  /**
   * US 1.5 : Déconnexion.
   *
   * CA-1 : envoie le cookie httpOnly (withCredentials) au backend qui révoque le refresh
   *        token en base et vide le cookie via Set-Cookie.
   * CA-2 : envoie le JWT dans Authorization: Bearer → backend l'inscrit en blocklist Redis.
   * CA-3 : après confirmation serveur (ou même en cas d'erreur réseau), l'état local est
   *        effacé et l'utilisateur est redirigé vers la page d'accueil publique ("/").
   */
  logout(): void {
    const token = localStorage.getItem('token');

    const headers = token
      ? new HttpHeaders({ Authorization: `Bearer ${token}` })
      : new HttpHeaders();

    // On appelle l'API puis on nettoie, que la requête réussisse ou échoue.
    // L'utilisateur ne doit jamais rester bloqué sur une page protégée à cause
    // d'un problème réseau lors de la déconnexion.
    this.#http
      .post<AuthMessageResponse>(
        // La route de logout est sous /api/* (firewall Symfony qui vérifie le JWT)
        `${environment.apiUrl}/auth/logout`,
        {},
        { headers, withCredentials: true },
      )
      .subscribe({
        next:     () => this.#clearSessionAndRedirect(),
        error:    () => this.#clearSessionAndRedirect(), // CA-3 : on nettoie même en cas d'erreur
      });
  }

  // ── Helpers privés ───────────────────────────────────────

  #clearSessionAndRedirect(): void {
    // CA-1 : suppression du JWT stocké localement
    localStorage.removeItem('token');

    // Réinitialisation du signal d'état
    this.currentUser.set(null);

    // CA-3 : redirection vers la page d'accueil publique
    this.#router.navigate(['/']);
  }

  private decodeAndSetUser(token: string): void {
    try {
      const decoded: { roles: MenuRole[]; username: string; iat: number; exp: number; id: number } =
        jwtDecode(token);

      this.currentUser.set({
        id:       decoded.id,
        username: decoded.username,
        role:     decoded.roles[0] ?? 'VISITEUR',
      });
    } catch (error) {
      console.error('Failed to decode token:', error);
      this.currentUser.set(null);
    }
  }
}