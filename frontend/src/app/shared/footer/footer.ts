import { Component, inject, signal } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-footer',
  imports: [MatIconModule, MatToolbarModule, MatButtonModule, RouterModule],
  templateUrl: './footer.html',
  styleUrls: ['./footer.scss'],
})
export class Footer {
  readonly #http = inject(HttpClient);

  email     = signal('');
  isLoading = signal(false);
  success   = signal(false);
  error     = signal('');

  subscribe(): void {
    const emailValue = this.email().trim();

    if (!emailValue || !this.isValidEmail(emailValue)) {
      this.error.set('Veuillez saisir une adresse email valide.');
      return;
    }

    this.isLoading.set(true);
    this.error.set('');

    this.#http.post(`${environment.apiUrl}/newsletter/subscribe`, {
      email: emailValue,
    }).subscribe({
      next: () => {
        this.success.set(true);
        this.isLoading.set(false);
        this.email.set('');
      },
      error: (err) => {
        this.isLoading.set(false);
        if (err.status === 409) {
          this.error.set('Cette adresse est déjà inscrite à la newsletter.');
        } else {
          this.error.set('Une erreur est survenue. Veuillez réessayer.');
        }
      }
    });
  }

  private isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }
}