import { computed, inject, Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
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
  email:    string;
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
  readonly #apiUrl = `${environment.apiUrl}/auth`;

  // --- State management ---
  currentUser = signal<User | null | undefined>(undefined);
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

  // CA-1 : withCredentials obligatoire pour recevoir le cookie refresh token httpOnly
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

  logout(): void {
    this.currentUser.set(null);
    localStorage.removeItem('token');
  }

  private decodeAndSetUser(token: string): void {
    try {
      const decodedToken: { roles: MenuRole[], username: string, iat: number, exp: number, id: number } = jwtDecode(token);
      const user: User = {
        id:       decodedToken.id,
        username: decodedToken.username,
        role:     decodedToken.roles[0] ?? 'VISITEUR',
        
      };
         
      this.currentUser.set(user);
    } catch (error) {
      console.error('Failed to decode token:', error);
      this.currentUser.set(null);
    }

  }
}