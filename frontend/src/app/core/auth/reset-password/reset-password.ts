import { Component, inject, OnDestroy, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth';
import { SharedModule } from '../../../shared/shared-module';
import { HeaderImage } from '../../../shared/header-image/header-image';
import { Header } from '../../../shared/header/header';
import { Footer } from '../../../shared/footer/footer';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, SharedModule, HeaderImage, Header, Footer],
  templateUrl: './reset-password.html',
  styleUrls: ['./reset-password.scss'],
})
export class ResetPassword implements OnDestroy {

  readonly #fb          = inject(FormBuilder);
  readonly #authService = inject(AuthService);
  readonly #destroy$    = new Subject<void>();

  form = this.#fb.group({
    email: ['', [Validators.required, Validators.email]],
  });

  isSubmitted  = signal(false);
  isLoading    = signal(false);
  // CA-1 : message unique affiché après soumission, qu'importe si l'email existe
  successShown = signal(false);

  get emailCtrl() { return this.form.get('email')!; }

  onSubmit(): void {
    this.isSubmitted.set(true);
    if (this.form.invalid || this.isLoading()) return;

    this.isLoading.set(true);

    this.#authService
      .requestPasswordReset(this.emailCtrl.value ?? '')
      .pipe(takeUntil(this.#destroy$))
      .subscribe({
        // CA-1 : on affiche le même message succès quelle que soit la réponse (202 ou erreur réseau)
        next:  () => { this.isLoading.set(false); this.successShown.set(true); },
        error: () => { this.isLoading.set(false); this.successShown.set(true); },
      });
  }

  ngOnDestroy(): void {
    this.#destroy$.next();
    this.#destroy$.complete();
  }
}