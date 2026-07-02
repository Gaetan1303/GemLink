import { Component, inject, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth';
import { SharedModule } from '../../../shared/shared-module';
import { HeaderImage } from '../../../shared/header-image/header-image';
import { Header } from '../../../shared/header/header';
import { Footer } from '../../../shared/footer/footer';

// CA-4 : même regex que l'inscription (US 1.1 CA-2) et que le backend
const PASSWORD_PATTERN = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/;

function passwordMatchValidator(control: AbstractControl): ValidationErrors | null {
  const pwd     = control.get('password');
  const confirm = control.get('confirmPassword');
  if (pwd && confirm && pwd.value !== confirm.value) {
    return { mismatch: true };
  }
  return null;
}

@Component({
  selector: 'app-reset-password-confirm',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, SharedModule, HeaderImage, Header, Footer],
  templateUrl: './reset-password-confirm.html',
  styleUrls: ['./reset-password-confirm.scss'],
})
export class ResetPasswordConfirm implements OnInit, OnDestroy {

  readonly #fb          = inject(FormBuilder);
  readonly #authService = inject(AuthService);
  readonly #route       = inject(ActivatedRoute);
  readonly #router      = inject(Router);
  readonly #destroy$    = new Subject<void>();

  /** Token brut extrait de l'URL (valeur signée HMAC transmise par email). */
  private rawToken = '';

  form = this.#fb.group(
    {
      password: ['', [
        Validators.required,
        Validators.minLength(8),
        Validators.pattern(PASSWORD_PATTERN),
      ]],
      confirmPassword: ['', Validators.required],
    },
    { validators: passwordMatchValidator }
  );

  isSubmitted  = signal(false);
  isLoading    = signal(false);
  serverError  = signal<string | null>(null);
  successShown = signal(false);
  // CA-2 : token absent ou invalide dès le chargement de la page
  tokenInvalid = signal(false);

  get passwordCtrl()        { return this.form.get('password')!; }
  get confirmPasswordCtrl() { return this.form.get('confirmPassword')!; }

  ngOnInit(): void {
    const token = this.#route.snapshot.paramMap.get('token') ?? '';

    if (!token) {
      this.tokenInvalid.set(true);
      return;
    }

    this.rawToken = token;
  }

  onSubmit(): void {
    this.isSubmitted.set(true);
    this.serverError.set(null);

    if (this.form.invalid || this.isLoading()) return;

    this.isLoading.set(true);

    this.#authService
      .resetPassword(this.rawToken, this.passwordCtrl.value ?? '')
      .pipe(takeUntil(this.#destroy$))
      .subscribe({
        next: () => {
          this.isLoading.set(false);
          this.successShown.set(true);
          // CA-3 : toutes les sessions ont été révoquées côté backend.
          // On s'assure que la session locale est aussi nettoyée.
          localStorage.removeItem('token');
          // Redirection automatique vers /auth/login après 3 secondes
          setTimeout(() => this.#router.navigate(['/auth/login']), 3000);
        },
        error: (err: { status?: number; error?: { message?: string } }) => {
          this.isLoading.set(false);

          if (err.status === 400) {
            const msg = err.error?.message ?? 'Lien invalide ou expiré.';
            // CA-2 : token expiré ou déjà utilisé → on bascule sur l'état d'erreur token
            if (msg.toLowerCase().includes('expiré') || msg.toLowerCase().includes('invalide') || msg.toLowerCase().includes('utilisé')) {
              this.tokenInvalid.set(true);
              return;
            }
            // CA-4 : erreur de politique de mot de passe
            this.serverError.set(msg);
            return;
          }

          this.serverError.set('Une erreur est survenue. Veuillez réessayer.');
        },
      });
  }

  ngOnDestroy(): void {
    this.#destroy$.next();
    this.#destroy$.complete();
  }
}