import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService, Publication } from '../../../core/services/post';

// US 2.2 — Consultation des posts : feed public paginé.
const PAGE_SIZE = 20;

@Component({
  selector: 'app-post-list',
  imports: [CommonModule, RouterLink, SharedModule, NavBarMobile],
  templateUrl: './post-list.html',
  styleUrls: ['./post-list.scss'],
})
export class PostList implements OnInit {

  private readonly authService = inject(AuthService);
  private readonly postService = inject(PostService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly posts      = signal<Publication[]>([]);
  protected readonly page       = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly total      = signal(0);
  protected readonly isLoading  = signal(false);
  protected readonly loadError  = signal<string | null>(null);

  protected readonly hasNextPage = computed(() => this.page() < this.totalPages());
  protected readonly hasPrevPage = computed(() => this.page() > 1);

  ngOnInit(): void {
    this.loadPage(1);
  }

  goToNextPage(): void {
    if (this.hasNextPage()) {
      this.loadPage(this.page() + 1);
    }
  }

  goToPrevPage(): void {
    if (this.hasPrevPage()) {
      this.loadPage(this.page() - 1);
    }
  }

  private loadPage(page: number): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.postService.listPosts(page, PAGE_SIZE).subscribe({
      next: (result) => {
        this.posts.set(result.items);
        this.page.set(result.page);
        this.totalPages.set(Math.max(1, result.totalPages));
        this.total.set(result.total);
        this.isLoading.set(false);
      },
      error: () => {
        this.loadError.set('Impossible de charger les posts pour le moment. Merci de réessayer.');
        this.isLoading.set(false);
      },
    });
  }
}
