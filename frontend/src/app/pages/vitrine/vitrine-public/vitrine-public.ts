import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PublicVitrine, PublicVitrineItem, VitrineService } from '../../../core/services/vitrine';

// US 4.2 - CA-1 : page publique d'une Vitrine, accessible sans
// authentification via son slug (URL canonique). Volontairement pas de
// bouton édition/suppression/reorder ici — c'est vitrine-detail (vue
// propriétaire) qui gère le CRUD ; cette page est purement consultative,
// exactement comme post-detail l'est pour un post.
@Component({
  selector: 'app-vitrine-public',
  imports: [CommonModule, SharedModule,],
  templateUrl: './vitrine-public.html',
  styleUrls: ['./vitrine-public.scss'],
})
export class VitrinePublic implements OnInit {

  private readonly route          = inject(ActivatedRoute);
  private readonly authService    = inject(AuthService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrine   = signal<PublicVitrine | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  ngOnInit(): void {
    const slug = this.route.snapshot.paramMap.get('slug');

    if (!slug) {
      this.loadError.set('Vitrine introuvable.');
      this.isLoading.set(false);
      return;
    }

    this.load(slug);
  }

  private load(slug: string): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.vitrineService.getPublicVitrine(slug).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.loadError.set(
          err?.status === 404
            ? 'Cette Vitrine n\'existe pas ou n\'est plus publiée.'
            : 'Impossible de charger cette Vitrine pour le moment.'
        );
        this.isLoading.set(false);
      },
    });
  }

  protected itemThumbnailUrl(item: PublicVitrineItem): string {
    return item.type === 'post' ? (item.publication?.mediaUrl ?? '') : (item.mediaUrl ?? '');
  }

  protected itemMediaType(item: PublicVitrineItem): 'IMAGE' | 'VIDEO' {
    return item.type === 'post' ? (item.publication?.mediaType ?? 'IMAGE') : (item.mediaType ?? 'IMAGE');
  }

  protected itemLabel(item: PublicVitrineItem): string {
    return item.type === 'post' ? (item.publication?.title || 'Sans titre') : 'Photo/vidéo';
  }
}
