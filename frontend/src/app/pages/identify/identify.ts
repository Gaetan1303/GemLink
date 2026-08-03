import { Component, OnDestroy, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { Subscription, timer } from 'rxjs';
import { switchMap, takeWhile } from 'rxjs/operators';
import { SharedModule } from '../../shared/shared-module';
import { AuthService } from '../../core/services/auth';
import { PostService, PublicIdentification } from '../../core/services/post';

@Component({ selector: 'app-identify', 
  imports: [CommonModule, SharedModule], 
  templateUrl: './identify.html', 
  styleUrls: ['./identify.scss'] })
  
export class Identify implements OnDestroy {
  protected readonly auth = inject(AuthService);
  private readonly posts = inject(PostService);
  private readonly router = inject(Router);
  protected readonly file = signal<File | null>(null);
  protected readonly preview = signal<string | null>(null);
  protected readonly identification = signal<PublicIdentification | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly isSubmitting = signal(false);
  protected readonly canSubmit = computed(() => this.file() !== null && !this.isSubmitting());
  private poll?: Subscription;

  onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    this.error.set(null); this.file.set(null); this.preview.set(null);
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 1024 * 1024) {
      this.error.set('Choisissez une image JPEG, PNG ou WebP de 1 Mo maximum.'); return;
    }
    this.file.set(file); this.preview.set(URL.createObjectURL(file));
  }
  submit(): void {
    const file = this.file(); if (!file) return;
    this.isSubmitting.set(true); this.error.set(null);
    this.posts.identifyPublic(file).subscribe({
      next: identification => { this.identification.set(identification); this.isSubmitting.set(false); this.watch(identification.id); },
      error: err => { this.isSubmitting.set(false); this.error.set(err?.error?.message ?? 'L’identification est indisponible pour le moment.'); },
    });
  }
  publish(): void { this.router.navigate(['/posts/new']); }
  ngOnDestroy(): void { this.poll?.unsubscribe(); const url = this.preview(); if (url) URL.revokeObjectURL(url); }
  private watch(id: string): void {
    this.poll?.unsubscribe();
    this.poll = timer(4000, 4000).pipe(switchMap(() => this.posts.getPublicIdentification(id)), takeWhile(value => value.status === 'PENDING_ANALYSIS', true)).subscribe({ next: value => this.identification.set(value), error: () => this.error.set('Le résultat n’est plus disponible.') });
  }
}
