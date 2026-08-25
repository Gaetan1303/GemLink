import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Header } from '../../../shared/header/header';
import { Button } from '../../../shared/button/button';
import { AuthService } from '../../../core/services/auth';
import { ProfileBadge, ProfilePoints, ProfileService, PublicProfile, PointsAction } from '../../../core/services/profile';
import { HeaderImage } from '../../../shared/header-image/header-image';
import { ChatService } from '../../../core/services/chat';


const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_AVATAR_SIZE_BYTES = 2 * 1024 * 1024;

@Component({
  selector: 'app-profile',
  imports: [CommonModule, ReactiveFormsModule, RouterLink, Header, HeaderImage, Button, MatCardModule, MatChipsModule, MatFormFieldModule, MatIconModule, MatInputModule, MatProgressSpinnerModule, MatProgressBarModule, MatTooltipModule],
  templateUrl: './profile.html',
  styleUrls: ['./profile.scss'],
})
export class Profile implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #profiles = inject(ProfileService);
  readonly #destroyRef = inject(DestroyRef);
  readonly #formBuilder = inject(FormBuilder);
  readonly #chat = inject(ChatService);
  readonly #router = inject(Router);

  protected readonly profile = signal<PublicProfile | null>(null);
  protected readonly points = signal<ProfilePoints | null>(null);
  protected readonly isPointsLoading = signal(false);
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly isOpeningConversation = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly selectedAvatar = signal<File | null>(null);
  protected readonly avatarPreview = signal<string | null>(null);
  protected readonly selectedBadge = signal<ProfileBadge | null>(null);
  protected readonly isOwnProfile = computed(() => this.profile()?.id === this.#auth.currentUser()?.id);
  protected readonly canMessage = computed(() => !!this.#auth.currentUser() && !this.isOwnProfile());
  protected readonly form = this.#formBuilder.nonNullable.group({
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9]{3,30}$/)]],
    bio: ['', [Validators.maxLength(500)]],
  });

  ngOnInit(): void {
    this.#route.paramMap.pipe(takeUntilDestroyed(this.#destroyRef)).subscribe((params) => {
      const requestedId = params.get('id');
      if (requestedId) {
        this.loadProfile(requestedId);
        return;
      }

      const userId = this.#auth.currentUser()?.id;
      if (userId) {
        this.loadProfile(userId);
        return;
      }

      // Les JWT émis avant US 1.7 ne portaient pas l'UUID : le refresh
      // transparent les remplace par un jeton compatible sans forcer un logout.
      this.#auth.refresh().pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
        next: () => {
          const refreshedId = this.#auth.currentUser()?.id;
          if (refreshedId) this.loadProfile(refreshedId);
          else this.showAuthenticationError();
        },
        error: () => this.showAuthenticationError(),
      });
    });
  }

  protected selectAvatar(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file) return;
    if (!ALLOWED_AVATAR_TYPES.includes(file.type)) {
      this.errorMessage.set('Formats acceptés : JPEG, PNG ou WebP.'); input.value = ''; return;
    }
    if (file.size > MAX_AVATAR_SIZE_BYTES) {
      this.errorMessage.set('L’avatar ne peut pas dépasser 2 Mo.'); input.value = ''; return;
    }
    const oldPreview = this.avatarPreview();
    if (oldPreview) URL.revokeObjectURL(oldPreview);
    this.selectedAvatar.set(file);
    this.avatarPreview.set(URL.createObjectURL(file));
    this.errorMessage.set(null);
  }

  protected openBadge(badge: ProfileBadge): void { this.selectedBadge.set(badge); }
  protected closeBadge(): void { this.selectedBadge.set(null); }

  protected save(): void {
    const current = this.profile();
    this.form.markAllAsTouched();
    if (!current || !this.isOwnProfile() || this.form.invalid) return;
    this.isSaving.set(true); this.errorMessage.set(null);
    const value = this.form.getRawValue();
    this.#profiles.updateProfile(current.id, value.username, value.bio, this.selectedAvatar())
      .pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
        next: (profile) => { this.applyProfile(profile); this.selectedAvatar.set(null); this.isSaving.set(false); },
        error: (error) => { this.errorMessage.set(error.error?.message ?? 'Impossible d’enregistrer le profil.'); this.isSaving.set(false); },
      });
  }

  protected openConversation(): void {
    const target = this.profile();
    if (!target || !this.canMessage() || this.isOpeningConversation()) return;
    this.isOpeningConversation.set(true);
    this.errorMessage.set(null);
    this.#chat.direct(target.id).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
      next: conversation => this.#router.navigate(['/messages', conversation.id]),
      error: error => {
        this.errorMessage.set(error.error?.message ?? 'Impossible d’ouvrir la conversation.');
        this.isOpeningConversation.set(false);
      },
    });
  }

  private loadProfile(userId: string): void {
    this.isLoading.set(true); this.errorMessage.set(null);
    this.#profiles.getProfile(userId).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
      next: (profile) => { this.applyProfile(profile); this.isLoading.set(false); },
      error: () => { this.profile.set(null); this.errorMessage.set('Profil introuvable ou indisponible.'); this.isLoading.set(false); },
    });
  }

  private applyProfile(profile: PublicProfile): void {
    this.profile.set(profile);
    this.form.setValue({ username: profile.username, bio: profile.bio ?? '' });

    if (profile.id === this.#auth.currentUser()?.id) {
      this.loadPoints(profile.id);
    } else {
      this.points.set(null);
    }
  }

  protected pointsActionLabel(action: PointsAction): string {
    const labels: Record<PointsAction, string> = {
      POST_CREATED: 'Publication créée',
      LIKE_RECEIVED: 'Like reçu',
      VALIDATION_SUBMITTED: 'Validation soumise',
      VALIDATION_CONSENSUS_CONFIRMED: 'Validation confirmée par consensus',
    };

    return labels[action];
  }

  private loadPoints(userId: string): void {
    this.isPointsLoading.set(true);
    this.#profiles.getPoints(userId).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
      next: (points) => {
        if (this.profile()?.id === userId) this.points.set(points);
        this.isPointsLoading.set(false);
      },
      // The profile remains usable when the asynchronous points endpoint is
      // temporarily unavailable; a later profile visit retries automatically.
      error: () => {
        if (this.profile()?.id === userId) this.points.set(null);
        this.isPointsLoading.set(false);
      },
    });
  }

  private showAuthenticationError(): void {
    this.isLoading.set(false);
    this.errorMessage.set('Votre session a expiré. Connectez-vous pour consulter votre profil.');
  }
}
