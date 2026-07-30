import { AfterViewInit, Component, ElementRef, OnDestroy, ViewChild, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { PostService, Publication } from '../../core/services/post';

@Component({
  selector: 'app-latest-discoveries',
  imports: [CommonModule, RouterLink],
  templateUrl: './latest-discoveries.html',
  styleUrls: ['./latest-discoveries.scss'],
})
export class LatestDiscoveries implements AfterViewInit, OnDestroy {
  private readonly postsService = inject(PostService);
  private observer?: IntersectionObserver;
  @ViewChild('feedSentinel') private sentinel?: ElementRef<HTMLElement>;

  protected readonly posts = signal<Publication[]>([]);
  protected readonly isLoading = signal(false);
  protected readonly loadError = signal<string | null>(null);
  private nextCursor: string | null = null;
  private hasNextPage = true;

  ngAfterViewInit(): void {
    this.loadNext();
    this.observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) this.loadNext();
    }, { rootMargin: '300px' });
    if (this.sentinel) this.observer.observe(this.sentinel.nativeElement);
  }

  ngOnDestroy(): void { this.observer?.disconnect(); }

  protected loadNext(): void {
    if (this.isLoading() || !this.hasNextPage) return;
    this.isLoading.set(true);
    this.loadError.set(null);
    this.postsService.listPosts(this.nextCursor).subscribe({
      next: page => {
        this.posts.update(posts => [...posts, ...page.items]);
        this.nextCursor = page.nextCursor;
        this.hasNextPage = page.hasNextPage;
        this.isLoading.set(false);
      },
      error: () => {
        this.loadError.set('Les dernières découvertes sont indisponibles pour le moment.');
        this.isLoading.set(false);
      },
    });
  }
}
