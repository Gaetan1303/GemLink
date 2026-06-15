import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment'; // À adapter selon tes fichiers de config

export interface RegisterRequest {
  username: string;
  email: string;
  passwordHash: string; // Ton back attend le mot de passe (le hashage Argon2id se faisant côté serveur au moment de la réception, cf CA-2)
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/auth`; // ex: http://localhost:8000/api/auth

  register(data: RegisterRequest): Observable<void> {
    return this.http.post<void>(`${this.apiUrl}/register`, data);
  }
}