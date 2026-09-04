import { Component, Input, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/services/auth';
import { Comment, CommentService, COMMENT_MAX_LENGTH } from '../../core/services/comment';

/**
 * US 2.4 — Commentaires MVP (CA-1 à CA-4).
 * Autonome : charge sa première page au montage, gère elle-même la
 * pagination cursor-based (CA-3), la création (CA-1) et la suppression
 * avec confirmation (CA-2). La notification de l'auteur du post (CA-4)
 * est un effet de bord silencieux côté serveur, rien à afficher ici.
 */
@Component({
  selector: 'app-comment-section',
  imports: [CommonModule, FormsModule,],
  templateUrl: './comment-section.html',
  styleUrls: ['./comment-section.scss'],
})
export class CommentSection implements OnInit {

  @Input({ required: true }) postId!: string;

  private readonly authService    = inject(AuthService);
  private readonly commentService = inject(CommentService);

  protected readonly maxLength = COMMENT_MAX_LENGTH;

  protected readonly comments      = signal<Comment[]>([]);
  protected readonly nextCursor    = signal<string | null>(null);
  protected readonly isLoading     = signal(true);
  protected readonly isLoadingMore = signal(false);
  protected readonly loadError     = signal<string | null>(null);

  protected readonly newContent   = signal('');
  protected readonly isSubmitting = signal(false);
  protected readonly submitError  = signal<string | null>(null);

  protected readonly confirmDeleteId = signal<string | null>(null);
  protected readonly deletingId      = signal<string | null>(null);
  protected readonly deleteError     = signal<string | null>(null);

  protected readonly isAuthenticated = computed(() => this.authService.isAuthenticated());

  protected readonly remainingChars = computed(() => this.maxLength - this.newContent().trim().length);

  protected readonly canSubmit = computed(() => {
    const trimmed = this.newContent().trim();
    return trimmed.length > 0 && trimmed.length <= this.maxLength && !this.isSubmitting();
  });

  ngOnInit(): void {
    this.loadFirstPage();
  }

  private loadFirstPage(): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.commentService.listComments(this.postId).subscribe({
      next: (page) => {
        this.comments.set(page.items);
        this.nextCursor.set(page.nextCursor);
        this.isLoading.set(false);
      },
      error: () => {
        this.loadError.set('Impossible de charger les commentaires pour le moment.');
        this.isLoading.set(false);
      },
    });
  }

  /**
   * CA-3 : charge la page suivante à partir du curseur courant et
   * concatène les résultats à la suite (ordre chronologique croissant).
   */
  loadMore(): void {
    const cursor = this.nextCursor();

    if (cursor === null || this.isLoadingMore()) {
      return;
    }

    this.isLoadingMore.set(true);

    this.commentService.listComments(this.postId, cursor).subscribe({
      next: (page) => {
        this.comments.update((current) => [...current, ...page.items]);
        this.nextCursor.set(page.nextCursor);
        this.isLoadingMore.set(false);
      },
      error: () => {
        this.isLoadingMore.set(false);
      },
    });
  }

  submit(): void {
    if (!this.canSubmit()) {
      return;
    }

    const content = this.newContent().trim();
    const validationError = this.commentService.validateContent(content);

    if (validationError) {
      this.submitError.set(validationError);
      return;
    }

    this.isSubmitting.set(true);
    this.submitError.set(null);

    this.commentService.createComment(this.postId, content).subscribe({
      next: (comment) => {
        // CA-3 : ordre chronologique croissant, le nouveau commentaire va donc en dernier.
        this.comments.update((current) => [...current, comment]);
        this.newContent.set('');
        this.isSubmitting.set(false);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.submitError.set(err?.error?.message ?? 'L\'envoi du commentaire a échoué. Merci de réessayer.');
      },
    });
  }

  confirmDelete(commentId: string): void {
    this.confirmDeleteId.set(commentId);
  }

  cancelDelete(): void {
    this.confirmDeleteId.set(null);
  }

  deleteComment(commentId: string): void {
    this.deletingId.set(commentId);
    this.deleteError.set(null);

    this.commentService.deleteComment(commentId).subscribe({
      next: () => {
        this.comments.update((current) => current.filter((c) => c.id !== commentId));
        this.confirmDeleteId.set(null);
        this.deletingId.set(null);
      },
      error: (err) => {
        this.deletingId.set(null);
        this.confirmDeleteId.set(null);
        this.deleteError.set(err?.error?.message ?? 'La suppression a échoué. Merci de réessayer.');
      },
    });
  }

  /**
   * CA-2 : l'autorisation finale est toujours vérifiée côté serveur ; ceci
   * ne fait que masquer/afficher le bouton pour l'UX (même limite que
   * PostDetail::canDelete — le rôle MODERATOR n'est pas encore distingué
   * côté frontend).
   */
  canDeleteComment(comment: Comment): boolean {
    const user = this.authService.currentUser();

    if (!user) {
      return false;
    }

    return user.role === 'ROLE_ADMIN' || String(user.id) === comment.author.id;
  }
}
