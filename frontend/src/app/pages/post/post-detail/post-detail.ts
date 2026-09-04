import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { finalize, Subscription } from 'rxjs';
import { animate, style, transition, trigger } from '@angular/animations';
import { SharedModule } from '../../../shared/shared-module';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService, Publication, SimilarPublication } from '../../../core/services/post';
import { CommentSection } from '../../../shared/comment-section/comment-section';
import { ValidationWidget } from '../../../shared/validation-widget/validation-widget';
import { ReportReason, ReportService } from '../../../core/services/report';
import { PostAnalysisResult } from '../post-analysis-result/post-analysis-result';

// US 2.2 — Consultation des posts : détail public d'un post.
// US 3.1 — Suivi en direct de l'analyse IA (polling) + transitions animées.
@Component({
  selector: 'app-post-detail',
  imports: [CommonModule, ReactiveFormsModule, RouterLink, SharedModule, CommentSection, ValidationWidget, PostAnalysisResult],
  templateUrl: './post-detail.html',
  styleUrls: ['./post-detail.scss'],
  animations: [
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
  protected readonly authService = inject(AuthService);
  private readonly postService = inject(PostService);
  private readonly reportService = inject(ReportService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly post       = signal<Publication | null>(null);
  protected readonly isLoading  = signal(true);
  protected readonly loadError  = signal<string | null>(null);
  protected readonly similarPosts = signal<SimilarPublication[]>([]);
  protected readonly isSimilarLoading = signal(false);

  protected readonly isDeleting = signal(false);
  protected readonly deleteError = signal<string | null>(null);
  protected readonly showDeleteConfirm = signal(false);
  protected readonly isLiking = signal(false);
  protected readonly likeError = signal<string | null>(null);
  protected readonly showReport = signal(false);
  protected readonly reportMessage = signal<string | null>(null);
  protected readonly isReporting = signal(false);
  protected readonly reportForm = this.formBuilder.nonNullable.group({
    reasonType: ['', Validators.required],
    description: ['', Validators.maxLength(1000)],
  });

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

  // US 2.7 CA-1 : un utilisateur authentifié peut valider l'identification
  // IA de n'importe quel post sauf le sien (pas de sens à s'auto-valider).
  // Même logique de "masquage UX seulement" que canDelete — le serveur
  // revérifie IS_AUTHENTICATED_FULLY de toute façon.
  protected readonly canValidate = computed(() => {
    const user = this.authService.currentUser();
    const currentPost = this.post();

    if (!user || !currentPost) {
      return false;
    }

    return String(user.id) !== currentPost.author.id;
  });

  protected readonly canReport = computed(() => {
    const user = this.authService.currentUser();
    const currentPost = this.post();
    return !!user && !!currentPost && String(user.id) !== currentPost.author.id;
  });

  protected openReportForm(): void {
    this.reportMessage.set(null);
    this.showReport.set(true);
  }

  protected cancelReport(): void {
    this.showReport.set(false);
    this.reportForm.reset({ reasonType: '', description: '' });
  }

  protected submitReport(): void {
    const currentPost = this.post();
    if (!currentPost || this.reportForm.invalid || this.isReporting()) return;

    const { reasonType, description } = this.reportForm.getRawValue();
    this.isReporting.set(true);
    this.reportService.create(currentPost.id, reasonType as ReportReason, description).pipe(
      finalize(() => this.isReporting.set(false)),
    ).subscribe({
      next: () => {
        this.reportMessage.set('Signalement transmis à la modération.');
        this.showReport.set(false);
        this.reportForm.reset({ reasonType: '', description: '' });
      },
      error: (error) => this.reportMessage.set(
        error?.status === 409
          ? 'Votre signalement a déjà été pris en compte.'
          : (error?.error?.message ?? 'Impossible de transmettre le signalement.')
      ),
    });
  }

  // US 3.1 : l'analyse IA est asynchrone côté serveur (CA-1/CA-2/CA-3). Tant
  // que le post arrive en PENDING_ANALYSIS, on ré-interroge l'API pour
  // révéler le résultat dès qu'il est prêt, sans que l'utilisateur ait à
  // recharger la page.
  private pollSubscription: Subscription | null = null;
  private routeSubscription: Subscription | null = null;

  ngOnInit(): void {
    this.routeSubscription = this.route.paramMap.subscribe(params => this.loadPost(params.get('id')));
  }

  private loadPost(id: string | null): void {
    this.pollSubscription?.unsubscribe();
    this.post.set(null);
    this.similarPosts.set([]);
    this.loadError.set(null);
    this.isLoading.set(true);
    this.isSimilarLoading.set(false);

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
        } else {
          this.loadSimilarPosts(post);
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
    this.routeSubscription?.unsubscribe();
  }

  confirmDelete(): void {
    this.showDeleteConfirm.set(true);
  }

  cancelDelete(): void {
    this.showDeleteConfirm.set(false);
  }

  protected toggleLike(): void {
    const currentPost = this.post();
    if (!currentPost || !this.authService.isAuthenticated() || this.isLiking()) {
      return;
    }

    const previousLiked = currentPost.likedByCurrentUser;
    const previousCount = currentPost.likeCount;
    const optimisticLiked = !previousLiked;
    const optimisticCount = Math.max(0, previousCount + (optimisticLiked ? 1 : -1));

    this.likeError.set(null);
    this.post.set({ ...currentPost, likedByCurrentUser: optimisticLiked, likeCount: optimisticCount });
    this.isLiking.set(true);

    this.postService.toggleLike(currentPost.id).pipe(
      finalize(() => this.isLiking.set(false)),
    ).subscribe({
      next: (result) => this.post.update(post => post && ({
        ...post,
        likedByCurrentUser: result.liked,
        likeCount: result.likeCount,
      })),
      error: () => {
        this.post.update(post => post && ({
          ...post,
          likedByCurrentUser: previousLiked,
          likeCount: previousCount,
        }));
        this.likeError.set('Le like n’a pas pu être enregistré. La modification a été annulée.');
      },
    });
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
      next: (post) => {
        this.post.set(post);
        if (post.status !== 'PENDING_ANALYSIS') this.loadSimilarPosts(post);
      },
      error: () => this.pollSubscription?.unsubscribe(),
    });
  }

  private loadSimilarPosts(post: Publication): void {
    if (post.status !== 'ANALYZED' && post.status !== 'COMMUNITY_VALIDATED') {
      this.similarPosts.set([]);
      this.isSimilarLoading.set(false);
      return;
    }

    this.isSimilarLoading.set(true);
    this.postService.getSimilarPosts(post.id, 5).pipe(
      finalize(() => this.isSimilarLoading.set(false)),
    ).subscribe({
      next: ({ items }) => {
        if (this.post()?.id === post.id) this.similarPosts.set(items.slice(0, 5));
      },
      error: () => this.similarPosts.set([]),
    });
  }

}
