import { Component, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { SharedModule } from "../../../shared/shared-module";
import { AuthService } from "../../../core/services/auth";
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-contact',
  imports: [SharedModule, ],
  templateUrl: './contact.html',
  styleUrls: ['./contact.scss'],
})
export class Contact {
  private readonly authService = inject(AuthService);
  readonly #http = inject(HttpClient);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  nom     = signal('');
  email   = signal('');
  sujet   = signal('');
  message = signal('');

  isLoading = signal(false);
  success   = signal(false);
  error     = signal('');

  submit(): void {
    if (!this.nom() || !this.email() || !this.message()) {
      this.error.set('Veuillez remplir tous les champs obligatoires.');
      return;
    }

    this.isLoading.set(true);
    this.error.set('');

    this.#http.post(`${environment.apiUrl}/contact`, {
      nom:     this.nom(),
      email:   this.email(),
      sujet:   this.sujet(),
      message: this.message(),
    }).subscribe({
      next: () => {
        this.success.set(true);
        this.isLoading.set(false);
      },
      error: () => {
        this.error.set('Une erreur est survenue. Veuillez réessayer.');
        this.isLoading.set(false);
      }
    });
  }
}
