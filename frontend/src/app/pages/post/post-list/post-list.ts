import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService, Publication } from '../../../core/services/post';
import { finalize } from 'rxjs';

// US 2.2 — Consultation des posts : feed public paginé.
const PAGE_SIZE = 20;

@Component({
  selector: 'app-post-list',
  imports: [CommonModule, RouterLink, SharedModule],
  templateUrl: './post-list.html',
  styleUrls: ['./post-list.scss'],
})
export class PostList implements OnInit {

  protected readonly authService = inject(AuthService);
  private readonly postService = inject(PostService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly posts      = signal<Publication[]>([]);
  protected readonly page       = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly total      = signal(0);
  protected readonly isLoading  = signal(false);
  protected readonly loadError  = signal<string | null>(null);
  protected readonly likeError  = signal<string | null>(null);
  protected readonly activeTag = signal<string | null>(null);
  protected readonly likingPostIds = signal<ReadonlySet<string>>(new Set());
  protected readonly heroPost = computed(() => this.posts().find(post =>
    post.mediaType === 'IMAGE'
    && (post.status === 'ANALYZED' || post.status === 'COMMUNITY_VALIDATED')
  ) ?? null);

  protected readonly hasNextPage = signal(false);
  protected readonly hasPrevPage = computed(() => this.page() > 1);

  ngOnInit(): void {
    const tag = this.route.snapshot.queryParamMap.get('tag')?.trim().replace(/^#+/, '') || null;
    this.activeTag.set(tag);
    this.loadPage(null);
  }

  protected clearTag(): void {
    this.activeTag.set(null);
    this.router.navigate(['/posts']);
    this.loadPage(null);
  }

  goToNextPage(): void {
    if (this.hasNextPage()) {
      this.loadPage(this.nextCursor);
    }
  }

  goToPrevPage(): void {
    if (this.hasPrevPage()) {
      this.loadPage(null);
    }
  }

  protected toggleLike(post: Publication): void {
    if (!this.authService.isAuthenticated() || this.likingPostIds().has(post.id)) {
      return;
    }

    const previousLiked = post.likedByCurrentUser;
    const previousCount = post.likeCount;
    const optimisticLiked = !previousLiked;
    const optimisticCount = Math.max(0, previousCount + (optimisticLiked ? 1 : -1));

    this.likeError.set(null);
    this.updatePostLike(post.id, optimisticLiked, optimisticCount);
    this.likingPostIds.update(ids => new Set(ids).add(post.id));

    this.postService.toggleLike(post.id).pipe(
      finalize(() => this.finishLike(post.id)),
    ).subscribe({
      next: (result) => this.updatePostLike(post.id, result.liked, result.likeCount),
      error: () => {
        this.updatePostLike(post.id, previousLiked, previousCount);
        this.likeError.set('Le like n’a pas pu être enregistré. La modification a été annulée.');
      },
    });
  }

  private updatePostLike(postId: string, likedByCurrentUser: boolean, likeCount: number): void {
    this.posts.update(posts => posts.map(post => post.id === postId
      ? { ...post, likedByCurrentUser, likeCount }
      : post
    ));
  }

  private finishLike(postId: string): void {
    this.likingPostIds.update(ids => {
      const next = new Set(ids);
      next.delete(postId);
      return next;
    });
  }

  private nextCursor: string | null = null;

  private loadPage(cursor: string | null): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    const tag = this.activeTag();
    const request = tag ? this.postService.listPosts(cursor, PAGE_SIZE, { tag }) : this.postService.listPosts(cursor, PAGE_SIZE);
    request.subscribe({
      next: (result) => {
        this.posts.set(result.items);
        this.nextCursor = result.nextCursor;
        this.hasNextPage.set(result.hasNextPage);
        this.page.update(value => cursor === null ? 1 : value + 1);
        this.total.set(this.posts().length);
        this.isLoading.set(false);
      },
      error: () => {
        this.loadError.set('Impossible de charger les posts pour le moment. Merci de réessayer.');
        this.isLoading.set(false);
      },
    });
  }
}
