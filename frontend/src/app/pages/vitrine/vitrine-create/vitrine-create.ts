import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { concatMap, from } from 'rxjs';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService } from '../../../core/services/post';
import { VitrineService, Vitrine } from '../../../core/services/vitrine';

interface SelectedFile {
  file:       File;
  previewUrl: string;
  isVideo:    boolean;
}

@Component({
  selector: 'app-vitrine-create',
  imports: [CommonModule, ReactiveFormsModule, SharedModule,],
  templateUrl: './vitrine-create.html',
  styleUrls: ['./vitrine-create.scss'],
})
export class VitrineCreate {

  private readonly fb             = inject(FormBuilder);
  private readonly router         = inject(Router);
  private readonly authService    = inject(AuthService);
  private readonly postService    = inject(PostService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrineForm: FormGroup = this.fb.group({
    title: ['', [Validators.required, Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(500)]],
  });

  protected readonly selectedFiles = signal<SelectedFile[]>([]);
  protected readonly fileErrors    = signal<string[]>([]);

  protected readonly isSubmitting  = signal(false);
  protected readonly submitError   = signal<string | null>(null);
  protected readonly uploadedCount = signal(0);
  protected readonly totalToUpload = signal(0);

  protected readonly createdVitrine = signal<Vitrine | null>(null);

  protected readonly showPublishConfirm = signal(false);
  protected readonly isPublishing       = signal(false);
  protected readonly publishError       = signal<string | null>(null);

  constructor() {
    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/auth/login']);
    }
  }

  onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    const errors: string[] = [];
    const accepted: SelectedFile[] = [];

    for (const file of files) {
      const error = this.postService.validateMediaFile(file);
      if (error) {
        errors.push(`${file.name} : ${error}`);
        continue;
      }
      accepted.push({ file, previewUrl: URL.createObjectURL(file), isVideo: file.type.startsWith('video/') });
    }

    this.fileErrors.set(errors);
    this.selectedFiles.update((current) => [...current, ...accepted]);
  }

  removeSelectedFile(index: number): void {
    this.selectedFiles.update((current) => {
      const removed = current[index];
      if (removed) URL.revokeObjectURL(removed.previewUrl);
      return current.filter((_, i) => i !== index);
    });
  }

  submit(): void {
    if (this.vitrineForm.invalid) {
      this.vitrineForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.submitError.set(null);

    const { title, description } = this.vitrineForm.value;

    this.vitrineService.createVitrine(title, description ?? '').subscribe({
      next: (vitrine) => this.uploadSelectedFiles(vitrine.id),
      error: (err) => {
        this.isSubmitting.set(false);
        this.submitError.set(err?.error?.message ?? 'Une erreur est survenue lors de la création. Merci de réessayer.');
      },
    });
  }

  private uploadSelectedFiles(vitrineId: string): void {
    const files = this.selectedFiles();

    if (files.length === 0) {
      this.finalizeCreation(vitrineId);
      return;
    }

    this.uploadedCount.set(0);
    this.totalToUpload.set(files.length);

    from(files).pipe(
      concatMap(({ file }) => this.vitrineService.addMedia(vitrineId, file)),
    ).subscribe({
      next: () => this.uploadedCount.update((n) => n + 1),
      error: () => {
        this.submitError.set('Certaines photos/vidéos n\'ont pas pu être ajoutées. Vous pourrez réessayer depuis la page de la Vitrine.');
        this.finalizeCreation(vitrineId);
      },
      complete: () => this.finalizeCreation(vitrineId),
    });
  }

  private finalizeCreation(vitrineId: string): void {
    this.vitrineService.getVitrine(vitrineId).subscribe({
      next: (vitrine) => {
        this.isSubmitting.set(false);
        this.createdVitrine.set(vitrine);
        this.selectedFiles().forEach((f) => URL.revokeObjectURL(f.previewUrl));
        this.selectedFiles.set([]);
      },
      error: () => {
        this.isSubmitting.set(false);
        this.router.navigate(['/vitrines', vitrineId]);
      },
    });
  }

  viewVitrine(): void {
    const vitrine = this.createdVitrine();
    if (vitrine) this.router.navigate(['/vitrines', vitrine.id]);
  }

  askPublishConfirmation(): void {
    this.publishError.set(null);
    this.showPublishConfirm.set(true);
  }

  cancelPublishConfirmation(): void {
    this.showPublishConfirm.set(false);
  }

  confirmPublish(): void {
    const vitrine = this.createdVitrine();
    if (!vitrine) return;

    this.isPublishing.set(true);
    this.publishError.set(null);

    this.vitrineService.publish(vitrine.id).subscribe({
      next: () => {
        this.isPublishing.set(false);
        this.showPublishConfirm.set(false);
        this.router.navigate(['/vitrines', vitrine.id]);
      },
      error: (err) => {
        this.isPublishing.set(false);
        this.publishError.set(err?.error?.message ?? 'La publication a échoué. Merci de réessayer.');
      },
    });
  }
}
