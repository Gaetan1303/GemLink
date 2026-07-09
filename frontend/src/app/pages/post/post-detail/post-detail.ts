import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService, Publication } from '../../../core/services/post';

// US 2.2 — Consultation des posts : détail public d'un post.
@Component({
  selector: 'app-post-detail',
  imports: [CommonModule, SharedModule, NavBarMobile],
  templateUrl: './post-detail.html',
  styleUrls: ['./post-detail.scss'],
})
export class PostDetail implements OnInit {

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
}
