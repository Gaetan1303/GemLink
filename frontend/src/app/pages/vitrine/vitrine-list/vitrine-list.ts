import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { VitrineService, Vitrine } from '../../../core/services/vitrine';

// US 4.1 — Liste des Vitrines de l'utilisateur connecté.
@Component({
  selector: 'app-vitrine-list',
  imports: [CommonModule, RouterLink, SharedModule, NavBarMobile],
  templateUrl: './vitrine-list.html',
  styleUrls: ['./vitrine-list.scss'],
})
export class VitrineList implements OnInit {

  private readonly authService    = inject(AuthService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrines  = signal<Vitrine[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  ngOnInit(): void {
    this.vitrineService.listMine().subscribe({
      next: (result) => {
        this.vitrines.set(result.items);
        this.isLoading.set(false);
      },
      error: () => {
        this.loadError.set('Impossible de charger vos Vitrines pour le moment.');
        this.isLoading.set(false);
      },
    });
  }
}