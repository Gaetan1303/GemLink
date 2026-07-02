import { Component, inject, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { interval, Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import { SharedModule } from '../shared/shared-module';
import { AuthService } from '../core/services/auth';
import { ResetPassword } from '../core/auth/reset-password/reset-password';
import { RouterLink } from '@angular/router';

// ── Constantes US 1.3 CA-3 ───────────────────────────────────
const MAX_ATTEMPTS   = 5;
const THROTTLE_DELAY = 30; // secondes

@Component({
  selector: 'app-form',
  imports: [CommonModule, ReactiveFormsModule, SharedModule, RouterLink],
  templateUrl: './form.html',
  styleUrls: ['./form.scss'],
})
export class Form implements OnInit, OnDestroy {

  // ── Injections ─────────────────────────────────────────────
  readonly #fb          = inject(FormBuilder);
  readonly #authService = inject(AuthService);
  readonly #router      = inject(Router);

  // ── State ──────────────────────────────────────────────────
  loginForm!: FormGroup;
  resendForm!: FormGroup;

  isSubmitted = signal(false);
  isLoading   = signal(false);

  // CA-2 : message générique serveur
  serverError = signal<string | null>(null);
  resendSuccess = signal<string | null>(null);
  resendError = signal<string | null>(null);
  isResendLoading = signal(false);
  showResendForm = signal(false);

  // CA-3 : throttle
  isThrottled              = signal(false);
  throttleRemainingSeconds = signal(0);

  #failedAttempts = 0;
  #throttleSub$: Subscription | null = null;
  #destroy$       = new Subject<void>();

  // ── Init ───────────────────────────────────────────────────
  ngOnInit(): void {
    this.loginForm = this.#fb.group({
      email:    ['', [Validators.required, Validators.email]],
      passwordHash: ['', Validators.required],
    });

    this.resendForm = this.#fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  // ── Accesseurs ─────────────────────────────────────────────
  get emailCtrl()    { return this.loginForm.get('email')!;    }
  get passwordHashCtrl() { return this.loginForm.get('passwordHash')!; }
  get resendEmailCtrl() { return this.resendForm.get('email')!; }

  // ── Soumission ─────────────────────────────────────────────
  onSubmit(): void {
    this.isSubmitted.set(true);
    this.serverError.set(null);

    if (this.loginForm.invalid || this.isThrottled()) return;

    this.isLoading.set(true);

    this.#authService
      .login(this.loginForm.value)
      .pipe(takeUntil(this.#destroy$))
      .subscribe({
        next: () => {
          // CA-1 : JWT reçu dans le body, refresh token posé en cookie httpOnly par le navigateur
          this.isLoading.set(false);
          this.#failedAttempts = 0;
          this.#router.navigate(['/']);
        },
        error: (err: { status?: number }) => {
          this.isLoading.set(false);

          // CA-3 : 429 serveur → throttle immédiat
          if (err.status === 429) {
            this.#activateThrottle();
            return;
          }

          // CA-2 : message identique quel que soit le motif (401, 403, 404…)
          this.serverError.set('Identifiants incorrects.');
          this.#failedAttempts++;

          // CA-3 : 5 échecs → throttle client
          if (this.#failedAttempts >= MAX_ATTEMPTS) {
            this.#activateThrottle();
          }
        },
      });
  }

  onResendValidationEmail(): void {
    this.resendSuccess.set(null);
    this.resendError.set(null);

    if (this.resendForm.invalid) {
      this.resendForm.markAllAsTouched();
      return;
    }

    this.isResendLoading.set(true);
    this.#authService
      .resendValidationEmail(this.resendForm.value.email ?? '')
      .pipe(takeUntil(this.#destroy$))
      .subscribe({
        next: (response) => {
          this.isResendLoading.set(false);
          this.resendSuccess.set(response.message);
        },
        error: (err: { status?: number; error?: { message?: string } }) => {
          this.isResendLoading.set(false);

          if (err.status === 429) {
            this.resendError.set('Trop de demandes depuis cette adresse IP. Réessayez dans 1 heure.');
            return;
          }

          this.resendError.set(err.error?.message ?? 'Impossible de renvoyer l\'email de validation.');
        },
      });
  }

  toggleResendForm(): void {
    this.showResendForm.update((current) => !current);
  }

  // ── Throttle ───────────────────────────────────────────────
  #activateThrottle(): void {
    this.isThrottled.set(true);
    this.throttleRemainingSeconds.set(THROTTLE_DELAY);
    this.serverError.set(null);

    this.#throttleSub$?.unsubscribe();

    this.#throttleSub$ = interval(1000)
      .pipe(takeUntil(this.#destroy$))
      .subscribe(() => {
        this.throttleRemainingSeconds.update(s => s - 1);

        if (this.throttleRemainingSeconds() <= 0) {
          this.isThrottled.set(false);
          this.#failedAttempts = 0;
          this.#throttleSub$?.unsubscribe();
        }
      });
  }

  // ── Destroy ────────────────────────────────────────────────
  ngOnDestroy(): void {
    this.#destroy$.next();
    this.#destroy$.complete();
  }
}