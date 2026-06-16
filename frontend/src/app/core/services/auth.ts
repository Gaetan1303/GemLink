import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment'; 
export interface RegisterRequest {
  username: string;
  email: string;
  passwordHash: string; 
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/auth`; 

  register(data: RegisterRequest): Observable<void> {
    return this.http.post<void>(`${this.apiUrl}/register`, data);
  }
}