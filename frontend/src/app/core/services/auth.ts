import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

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
  password: string;
}

export interface LoginResponse {
  token: string;
}

// ── Service ──────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class AuthService {

  readonly #http   = inject(HttpClient);
  readonly #apiUrl = `${environment.apiUrl}/auth`;

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
    );
  }
}