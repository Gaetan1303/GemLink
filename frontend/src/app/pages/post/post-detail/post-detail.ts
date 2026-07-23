import { Component, ElementRef, OnDestroy, OnInit, ViewChild, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { animate, style, transition, trigger } from '@angular/animations';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService, Publication } from '../../../core/services/post';

// US 2.2 — Consultation des posts : détail public d'un post.
// US 3.1 — Suivi en direct de l'analyse IA (polling) + transitions animées.
@Component({
  selector: 'app-post-detail',
  imports: [CommonModule, SharedModule, NavBarMobile],
  templateUrl: './post-detail.html',
  styleUrls: ['./post-detail.scss'],
  animations: [
    // Révélation de la fiche minéralogique une fois l'analyse aboutie :
    // hauteur + fondu, pour ne pas faire "sauter" le layout brutalement.
    trigger('identificationReveal', [
      transition(':enter', [
        style({ height: 0, opacity: 0, transform: 'translateY(-6px)' }),
        animate(
          '420ms 100ms cubic-bezier(0.22, 1, 0.36, 1)',
          style({ height: '*', opacity: 1, transform: 'translateY(0)' }),
        ),
      ]),
    ]),
    // Disparition en fondu de l'overlay de scan dès que l'analyse se termine.
    trigger('scanFade', [
      transition(':leave', [
        animate('320ms ease-out', style({ opacity: 0 })),
      ]),
    ]),
  ],
})
export class PostDetail implements OnInit, OnDestroy {

  private readonly route       = inject(ActivatedRoute);
  private readonly router      = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly postService = inject(PostService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly post       = signal<Publication | null>(null);
  protected readonly isLoading  = signal(true);
  protected readonly loadError  = signal<string | null>(null);

  protected readonly isDeleting = signal(false);
  protected readonly deleteError = signal<string | null>(null);
  protected readonly showDeleteConfirm = signal(false);

  // CA-4 : l'autorisation finale est toujours vérifiée côté serveur ; ceci
  // ne fait que masquer/afficher le bouton pour l'UX (modérateur non
  // représenté dans MenuRole au stade actuel du frontend).
  protected readonly canDelete = computed(() => {
    const user = this.authService.currentUser();
    const currentPost = this.post();

    if (!user || !currentPost) {
      return false;
    }

    return user.role === 'ROLE_ADMIN' || String(user.id) === currentPost.author.id;
  });

  // US 3.1 : bouton dans la fiche d'identification qui fait défiler le
  // texte de la description (souvent longue — générée par l'agent
  // connaissance IA) sans que l'utilisateur ait à scroller manuellement
  // dans le petit encart. `{ static: false }` car l'élément est sous un
  // @if conditionnel (n'existe qu'une fois le post ANALYZED).
  @ViewChild('identificationDescriptionEl', { static: false })
  private identificationDescriptionEl?: ElementRef<HTMLElement>;

  protected readonly isDescriptionScrolledToEnd = signal(false);

  // US 3.1 : l'analyse IA est asynchrone côté serveur (CA-1/CA-2/CA-3). Tant
  // que le post arrive en PENDING_ANALYSIS, on ré-interroge l'API pour
  // révéler le résultat dès qu'il est prêt, sans que l'utilisateur ait à
  // recharger la page.
  private pollSubscription: Subscription | null = null;

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');

    if (!id) {
      this.loadError.set('Post introuvable.');
      this.isLoading.set(false);
      return;
    }

    this.postService.getPost(id).subscribe({
      next: (post) => {
        this.post.set(post);
        this.isLoading.set(false);

        if (post.status === 'PENDING_ANALYSIS') {
          this.watchAnalysis(id);
        }
      },
      error: (err) => {
        this.loadError.set(
          err?.status === 404
            ? 'Ce post n\'existe pas ou a été supprimé.'
            : 'Impossible de charger ce post pour le moment.'
        );
        this.isLoading.set(false);
      },
    });
  }

  ngOnDestroy(): void {
    this.pollSubscription?.unsubscribe();
  }

  confirmDelete(): void {
    this.showDeleteConfirm.set(true);
  }

  cancelDelete(): void {
    this.showDeleteConfirm.set(false);
  }

  deletePost(): void {
    const currentPost = this.post();
    if (!currentPost) {
      return;
    }

    this.isDeleting.set(true);
    this.deleteError.set(null);

    this.postService.deletePost(currentPost.id).subscribe({
      next: () => {
        this.router.navigate(['/posts']);
      },
      error: (err) => {
        this.isDeleting.set(false);
        this.showDeleteConfirm.set(false);
        this.deleteError.set(
          err?.error?.message ?? 'La suppression a échoué. Merci de réessayer.'
        );
      },
    });
  }

  /**
   * US 3.1 : suit le statut d'analyse via PostService.pollAnalysis, qui se
   * complète automatiquement dès que le post quitte PENDING_ANALYSIS. Une
   * erreur réseau ponctuelle pendant le polling n'efface pas l'affichage
   * déjà en place : le badge reste "en cours" et l'utilisateur peut
   * recharger la page manuellement si besoin.
   */
  private watchAnalysis(id: string): void {
    this.pollSubscription = this.postService.pollAnalysis(id).subscribe({
      next: (post) => this.post.set(post),
      error: () => this.pollSubscription?.unsubscribe(),
    });
  }

  /**
   * US 3.1 : fait défiler en douceur le texte de la description dans la
   * fiche d'identification (encart à hauteur limitée, cf. post-detail.scss).
   * Bascule : si on est déjà en bas, on remonte en haut plutôt que de
   * rester bloqué en fin de texte — un seul bouton fait l'aller-retour.
   */
  protected scrollIdentificationText(): void {
    const el = this.identificationDescriptionEl?.nativeElement;

    if (!el) {
      return;
    }

    const isNearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 4;

    el.scrollTo({
      top: isNearBottom ? 0 : el.scrollHeight,
      behavior: 'smooth',
    });

    this.isDescriptionScrolledToEnd.set(!isNearBottom);
  }
}
