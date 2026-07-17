import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { VitrineService } from '../../../core/services/vitrine';

// US 4.1 CA-1 — Création d'une Vitrine.
@Component({
  selector: 'app-vitrine-create',
  imports: [CommonModule, ReactiveFormsModule, SharedModule, NavBarMobile],
  templateUrl: './vitrine-create.html',
  styleUrls: ['./vitrine-create.scss'],
})
export class VitrineCreate {

  private readonly fb             = inject(FormBuilder);
  private readonly router         = inject(Router);
  private readonly authService    = inject(AuthService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrineForm: FormGroup = this.fb.group({
    title: ['', [Validators.required, Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(500)]],
  });

  protected readonly isSubmitting = signal(false);
  protected readonly submitError  = signal<string | null>(null);

  constructor() {
    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/auth/login']);
    }
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
      next: (vitrine) => {
        this.isSubmitting.set(false);
        this.router.navigate(['/vitrines', vitrine.id]);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.submitError.set(
          err?.error?.message ?? 'Une erreur est survenue lors de la création. Merci de réessayer.'
        );
      },
    });
  }
}