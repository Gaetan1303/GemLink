import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon'; 
import { MatToolbarModule } from '@angular/material/toolbar';
import { MenuBurger } from '../../components/menu-burger/menu-burger';
import { AuthService } from '../../core/services/auth';
import { MenuRole } from '../../components/menu-burger/menu-navigation.model';


@Component({
  selector: 'app-header',
  imports: [CommonModule, MatIconModule, MatToolbarModule, MenuBurger,],
  templateUrl: './header.html',
  styleUrls: ['./header.scss'],
})
export class Header {
  private authService = inject(AuthService);

  /**
   * Détermine le rôle du menu à afficher.
   * Retourne le rôle de l'utilisateur s'il est connecté, sinon 'VISITEUR'.
   */
  menuRole = computed<MenuRole>(() => this.authService.currentUser()?.role ?? 'VISITEUR');
}
