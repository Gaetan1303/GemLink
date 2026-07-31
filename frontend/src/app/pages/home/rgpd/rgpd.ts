import { Component, computed, inject } from '@angular/core';
import {SharedModule} from "../../../shared/shared-module";
import { NavBarMobile } from "../../../components/nav-bar-mobile/nav-bar-mobile";
import {AuthService} from "../../../core/services/auth";
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { HttpClient } from '@angular/common/http';
import { Header } from '../../../shared/header/header';
import { environment } from '../../../../environments/environment';
import { signal } from '@angular/core';

@Component({
  selector: 'app-rgpd',
  imports: [SharedModule,  Header],
  templateUrl: './rgpd.html',
  styleUrls: ['./rgpd.scss'],
})
export class Rgpd {
    private readonly authService = inject(AuthService);
    protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  readonly #http = inject(HttpClient);

  nom     = signal('');
  email   = signal('');
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

    this.#http.post(`${environment.apiUrl}/rgpd-request`, {
      nom:     this.nom(),
      email:   this.email(),
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





