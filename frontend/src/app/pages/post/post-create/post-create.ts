import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, AbstractControl, ValidatorFn } from '@angular/forms';
import { Router } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { PostService } from '../../../core/services/post';

// US 2.1 — Publication d'un post MVP.
const TAGS_MAX_COUNT = 10;

@Component({
  selector: 'app-post-create',
  imports: [CommonModule, ReactiveFormsModule, SharedModule,],
  templateUrl: './post-create.html',
  styleUrls: ['./post-create.scss'],
})
export class PostCreate {

  private readonly fb          = inject(FormBuilder);
  private readonly router      = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly postService = inject(PostService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly postForm: FormGroup = this.fb.group({
    title: ['', [Validators.maxLength(200)]],
    description: ['', [Validators.maxLength(2000)]],
    tagsInput: ['', [this.tagsCountValidator()]],
  });

  // ── Fichier média (CA-1, CA-2) ───────────────────────────────
  protected readonly selectedFile = signal<File | null>(null);
  protected readonly previewUrl   = signal<string | null>(null);
  protected readonly fileError    = signal<string | null>(null);

  // ── Soumission ────────────────────────────────────────────────
  protected readonly isSubmitting  = signal(false);
  protected readonly submitError   = signal<string | null>(null);

  protected readonly canSubmit = computed(() =>
    this.selectedFile() !== null
    && this.fileError() === null
    && this.postForm.valid
    && !this.isSubmitting()
  );

  constructor() {
    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/auth/login']);
    }
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    this.revokePreview();
    this.fileError.set(null);
    this.selectedFile.set(null);

    if (!file) {
      return;
    }

    // CA-2 : pré-validation côté client, la source de vérité reste le backend.
    const error = this.postService.validateMediaFile(file);
    if (error) {
      this.fileError.set(error);
      input.value = '';
      return;
    }

    this.selectedFile.set(file);

    if (file.type.startsWith('image/')) {
      this.previewUrl.set(URL.createObjectURL(file));
    }
  }

  removeFile(): void {
    this.revokePreview();
    this.selectedFile.set(null);
    this.fileError.set(null);
  }

  submit(): void {
    const file = this.selectedFile();

    // CA-1 : tout post sans fichier média valide est rejeté.
    if (!file) {
      this.fileError.set('Merci d\'ajouter une photo ou une courte vidéo de votre pierre.');
      return;
    }

    if (this.postForm.invalid) {
      this.postForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    // Les contrôles réactifs doivent être désactivés via leur FormGroup,
    // jamais via [disabled] dans le template (warning Angular NG01352).
    this.postForm.disable();
    this.submitError.set(null);

    const { title, description, tagsInput } = this.postForm.value;
    const tags = this.parseTags(tagsInput);

    this.postService.createPost(file, title ?? '', description ?? '', tags).subscribe({
      next: (publication) => {
        this.isSubmitting.set(false);
        // US 3.1 : on redirige vers le détail plutôt que d'afficher un écran
        // statique — c'est là que l'utilisateur voit l'analyse IA se
        // dérouler en direct (badge animé + overlay de scan).
        this.router.navigate(['/posts', publication.id]);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.postForm.enable();
        this.submitError.set(
          err?.error?.message ?? 'Une erreur est survenue lors de la publication. Merci de réessayer.'
        );
      },
    });
  }

  reset(): void {
    this.postForm.enable();
    this.submitError.set(null);
    this.removeFile();
    this.postForm.reset({ title: '', description: '', tagsInput: '' });
  }

  private parseTags(raw: string | null | undefined): string[] {
    if (!raw) {
      return [];
    }

    return raw
      .split(',')
      .map(tag => tag.trim().replace(/^#+/, ''))
      .filter(tag => tag.length > 0)
      .slice(0, TAGS_MAX_COUNT);
  }

  private tagsCountValidator(): ValidatorFn {
    return (control: AbstractControl) => {
      const count = this.parseTags(control.value).length;
      return count > TAGS_MAX_COUNT ? { tooManyTags: true } : null;
    };
  }

  private revokePreview(): void {
    const url = this.previewUrl();
    if (url) {
      URL.revokeObjectURL(url);
    }
    this.previewUrl.set(null);
  }
}
