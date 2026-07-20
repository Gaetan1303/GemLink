import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { concatMap, from } from 'rxjs';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService } from '../../../core/services/post';
import { VitrineService, Vitrine, VitrineItem, OrderedItemRef } from '../../../core/services/vitrine';

@Component({
  selector: 'app-vitrine-detail',
  imports: [CommonModule, RouterLink, ReactiveFormsModule, DragDropModule, SharedModule, NavBarMobile],
  templateUrl: './vitrine-detail.html',
  styleUrls: ['./vitrine-detail.scss'],
})
export class VitrineDetail implements OnInit {

  private readonly route          = inject(ActivatedRoute);
  private readonly router         = inject(Router);
  private readonly fb             = inject(FormBuilder);
  private readonly authService    = inject(AuthService);
  private readonly postService    = inject(PostService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrine   = signal<Vitrine | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  protected readonly canPublish = computed(() => (this.vitrine()?.itemsCount ?? 0) > 0);

  protected readonly isOwner = computed(
    () => this.authService.isAuthenticated() && this.vitrine() !== null
  );

  protected readonly editForm: FormGroup = this.fb.group({
    title: ['', [Validators.required, Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(500)]],
  });
  protected readonly isEditing = signal(false);
  protected readonly isSaving  = signal(false);
  protected readonly saveError = signal<string | null>(null);

  protected readonly addItemForm: FormGroup = this.fb.group({
    publicationId: ['', [Validators.required]],
  });
  protected readonly isAddingItem = signal(false);
  protected readonly addItemError = signal<string | null>(null);

  // Upload multiple de photos/vidéos.
  protected readonly isUploadingMedia = signal(false);
  protected readonly uploadMediaError = signal<string | null>(null);
  protected readonly uploadedCount    = signal(0);
  protected readonly totalToUpload    = signal(0);

  // Confirmation explicite avant publication.
  protected readonly showPublishConfirm = signal(false);
  protected readonly isPublishing        = signal(false);
  protected readonly publishError        = signal<string | null>(null);

  protected readonly isDeleting        = signal(false);
  protected readonly showDeleteConfirm = signal(false);
  protected readonly deleteError       = signal<string | null>(null);

  private vitrineId = '';

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');

    if (!id) {
      this.loadError.set('Vitrine introuvable.');
      this.isLoading.set(false);
      return;
    }

    this.vitrineId = id;
    this.load();
  }

  private load(): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.vitrineService.getVitrine(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.editForm.patchValue({ title: vitrine.title, description: vitrine.description ?? '' });
        this.isLoading.set(false);
      },
      error: (err) => {
        this.loadError.set(
          err?.status === 404
            ? 'Cette Vitrine n\'existe pas ou n\'est plus accessible.'
            : 'Impossible de charger cette Vitrine pour le moment.'
        );
        this.isLoading.set(false);
      },
    });
  }

  // ── CA-1 : édition ────────────────────────────────────────────

  startEditing(): void {
    this.saveError.set(null);
    this.isEditing.set(true);
  }

  cancelEditing(): void {
    const current = this.vitrine();
    if (current) {
      this.editForm.patchValue({ title: current.title, description: current.description ?? '' });
    }
    this.isEditing.set(false);
  }

  saveEdits(): void {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.saveError.set(null);

    const { title, description } = this.editForm.value;

    this.vitrineService.updateVitrine(this.vitrineId, title, description ?? null).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isSaving.set(false);
        this.isEditing.set(false);
      },
      error: (err) => {
        this.isSaving.set(false);
        this.saveError.set(err?.error?.message ?? 'La mise à jour a échoué. Merci de réessayer.');
      },
    });
  }

  // ── CA-2 : lier un post existant ──────────────────────────────

  addItem(): void {
    if (this.addItemForm.invalid) {
      this.addItemForm.markAllAsTouched();
      return;
    }

    this.isAddingItem.set(true);
    this.addItemError.set(null);

    const { publicationId } = this.addItemForm.value;

    this.vitrineService.addItem(this.vitrineId, String(publicationId).trim()).subscribe({
      next: () => {
        this.isAddingItem.set(false);
        this.addItemForm.reset({ publicationId: '' });
        this.load();
      },
      error: (err) => {
        this.isAddingItem.set(false);
        this.addItemError.set(err?.error?.message ?? 'Impossible d\'ajouter ce post à la Vitrine.');
      },
    });
  }

  // ── CA-2 (extension) : upload multiple de photos/vidéos ────────

  onMediaFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (files.length === 0) {
      return;
    }

    const errors: string[] = [];
    const validFiles: File[] = [];

    for (const file of files) {
      const error = this.postService.validateMediaFile(file);
      if (error) {
        errors.push(`${file.name} : ${error}`);
        continue;
      }
      validFiles.push(file);
    }

    if (errors.length > 0) {
      this.uploadMediaError.set(errors.join(' '));
    } else {
      this.uploadMediaError.set(null);
    }

    if (validFiles.length === 0) {
      return;
    }

    this.isUploadingMedia.set(true);
    this.uploadedCount.set(0);
    this.totalToUpload.set(validFiles.length);

    from(validFiles).pipe(
      concatMap((file) => this.vitrineService.addMedia(this.vitrineId, file)),
    ).subscribe({
      next: () => this.uploadedCount.update((n) => n + 1),
      error: (err) => {
        this.isUploadingMedia.set(false);
        this.uploadMediaError.set(err?.error?.message ?? 'L\'ajout de certains médias a échoué.');
        this.load();
      },
      complete: () => {
        this.isUploadingMedia.set(false);
        this.load();
      },
    });
  }

  // ── Suppression d'un item (post ou média) ───────────────────────

  removeItem(item: VitrineItem): void {
    const request$ = item.type === 'post'
      ? this.vitrineService.removeItem(this.vitrineId, item.id)
      : this.vitrineService.removeMedia(this.vitrineId, item.id);

    request$.subscribe({
      next: () => this.load(),
      error: (err) => {
        this.addItemError.set(err?.error?.message ?? 'La suppression a échoué.');
      },
    });
  }

  // ── CA-3 : glisser-déposer unifié ───────────────────────────────

  onItemDrop(event: CdkDragDrop<VitrineItem[]>): void {
    const current = this.vitrine();
    if (!current) {
      return;
    }

    const reordered = [...current.items];
    moveItemInArray(reordered, event.previousIndex, event.currentIndex);

    this.vitrine.set({ ...current, items: reordered });

    const orderedItems: OrderedItemRef[] = reordered.map((item) => ({ type: item.type, id: item.id }));

    this.vitrineService.reorderItems(this.vitrineId, orderedItems).subscribe({
      next: (vitrine) => this.vitrine.set(vitrine),
      error: () => this.load(),
    });
  }

  // ── CA-4 : confirmation puis publication ────────────────────────

  askPublishConfirmation(): void {
    this.publishError.set(null);
    this.showPublishConfirm.set(true);
  }

  cancelPublishConfirmation(): void {
    this.showPublishConfirm.set(false);
  }

  confirmPublish(): void {
    this.isPublishing.set(true);
    this.publishError.set(null);

    this.vitrineService.publish(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isPublishing.set(false);
        this.showPublishConfirm.set(false);
      },
      error: (err) => {
        this.isPublishing.set(false);
        this.publishError.set(err?.error?.message ?? 'La publication a échoué. Merci de réessayer.');
      },
    });
  }

  unpublish(): void {
    this.isPublishing.set(true);
    this.publishError.set(null);

    this.vitrineService.unpublish(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isPublishing.set(false);
      },
      error: (err) => {
        this.isPublishing.set(false);
        this.publishError.set(err?.error?.message ?? 'Une erreur est survenue. Merci de réessayer.');
      },
    });
  }

  // ── Suppression de la Vitrine ──────────────────────────────────

  confirmDelete(): void {
    this.showDeleteConfirm.set(true);
  }

  cancelDelete(): void {
    this.showDeleteConfirm.set(false);
  }

  deleteVitrine(): void {
    this.isDeleting.set(true);
    this.deleteError.set(null);

    this.vitrineService.deleteVitrine(this.vitrineId).subscribe({
      next: () => this.router.navigate(['/vitrines']),
      error: (err) => {
        this.isDeleting.set(false);
        this.showDeleteConfirm.set(false);
        this.deleteError.set(err?.error?.message ?? 'La suppression a échoué. Merci de réessayer.');
      },
    });
  }

  protected itemThumbnailUrl(item: VitrineItem): string {
    return item.type === 'post' ? (item.publication?.mediaUrl ?? '') : (item.mediaUrl ?? '');
  }

  protected itemMediaType(item: VitrineItem): 'IMAGE' | 'VIDEO' {
    return item.type === 'post' ? (item.publication?.mediaType ?? 'IMAGE') : (item.mediaType ?? 'IMAGE');
  }

  protected itemLabel(item: VitrineItem): string {
    return item.type === 'post' ? (item.publication?.title || 'Sans titre') : 'Photo/vidéo';
  }
}