import { Component, inject, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { interval, Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import { SharedModule } from '../shared/shared-module';
import { AuthService } from '../core/services/auth';

// ── Constantes US 1.3 CA-3 ───────────────────────────────────
const MAX_ATTEMPTS   = 5;
const THROTTLE_DELAY = 30; // secondes

@Component({
  selector: 'app-form',
  imports: [CommonModule, ReactiveFormsModule, SharedModule],
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

  isSubmitted = signal(false);
  isLoading   = signal(false);

  // CA-2 : message générique serveur
  serverError = signal<string | null>(null);

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
  }

  // ── Accesseurs ─────────────────────────────────────────────
  get emailCtrl()    { return this.loginForm.get('email')!;    }
  get passwordHashCtrl() { return this.loginForm.get('passwordHash')!; }

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