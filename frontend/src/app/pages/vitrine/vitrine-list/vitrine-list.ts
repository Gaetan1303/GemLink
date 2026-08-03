import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { VitrineService, Vitrine, VitrineItem } from '../../../core/services/vitrine';

interface CoverTile {
  url:     string;
  isVideo: boolean;
}

// US 4.1 — Liste des Vitrines, concept "écrin" : chaque carte affiche
// une mosaïque fixe de ses 3 premières pièces (jamais de carrousel —
// une grille statique avec badge "+N" pour le reste).
@Component({
  selector: 'app-vitrine-list',
  imports: [CommonModule, RouterLink, SharedModule, ],
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

  protected coverTiles(vitrine: Vitrine): CoverTile[] {
    return vitrine.items.slice(0, 3).map((item) => this.toTile(item));
  }

  protected overflowCount(vitrine: Vitrine): number {
    return Math.max(0, vitrine.itemsCount - 3);
  }

  private toTile(item: VitrineItem): CoverTile {
    if (item.type === 'post') {
      return { url: item.publication?.mediaUrl ?? '', isVideo: item.publication?.mediaType === 'VIDEO' };
    }
    return { url: item.mediaUrl ?? '', isVideo: item.mediaType === 'VIDEO' };
  }
}
