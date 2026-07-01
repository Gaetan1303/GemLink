import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, AbstractControl } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/services/auth';
import { SharedModule } from '../../shared/shared-module';

function passwordMatchValidator(control: AbstractControl): { mismatch: boolean } | null {
  const password = control.get('password');
  const confirmPassword = control.get('confirmPassword');
  if (password && confirmPassword && password.value !== confirmPassword.value) {
    return { mismatch: true };
  }
  return null;
}

@Component({
  selector: 'app-register-form',
  imports: [CommonModule, ReactiveFormsModule, SharedModule],
  templateUrl: './register-form.html',
  styleUrls: ['./register-form.scss'],
})
export class RegisterForm implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  registerForm!: FormGroup;
  resendForm!: FormGroup;
  isSubmitted = false;
  successMessage: string | null = null;
  errorMessage: string | null = null;
  resendSuccessMessage: string | null = null;
  resendErrorMessage: string | null = null;
  isResendLoading = false;
  showResendForm = false;

  ngOnInit(): void {
    this.registerForm = this.fb.group(
      {
        // CA-1 : pseudo alphanumérique, 3-30 caractères, saisi par l'utilisateur
        username: ['', [
          Validators.required,
          Validators.minLength(3),
          Validators.maxLength(30),
          Validators.pattern(/^[a-zA-Z0-9_]+$/),
        ]],
        // CA-1 : email format RFC 5322
        email: ['', [Validators.required, Validators.email]],
        // CA-2 : politique de sécurité mdp (vérif. front, le back revalide)
        password: ['', [
          Validators.required,
          Validators.minLength(8),
          Validators.pattern(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/),
        ]],
        confirmPassword: ['', Validators.required],
      },
      { validators: passwordMatchValidator }
    );

    this.resendForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  onSubmit(): void {
    this.isSubmitted = true;
    this.errorMessage = null;

    if (this.registerForm.invalid) {
      return;
    }

    const payload = {
      username: this.registerForm.value.username,
      email: this.registerForm.value.email,
      passwordHash: this.registerForm.value.password,
    };

    this.authService.register(payload).subscribe({
      next: () => {
        // CA-4 : compte en PENDING_VALIDATION, email envoyé en async
        this.successMessage =
          "Inscription réussie ! Un email de confirmation vous a été envoyé. Veuillez vérifier votre boîte mail.";
        this.registerForm.reset();
        this.isSubmitted = false;
        setTimeout(() => this.router.navigate(['/auth/login']), 5000);
      },
      error: (err) => {
        // CA-3 : message générique, pas d'énumération de comptes existants
        this.errorMessage =
          "Une erreur est survenue lors de l'inscription. Si ce compte existe déjà, un mail de validation lui a été renvoyé.";
        console.error(err);
      },
    });
  }

  onResendValidationEmail(): void {
    this.resendSuccessMessage = null;
    this.resendErrorMessage = null;

    if (this.resendForm.invalid) {
      this.resendForm.markAllAsTouched();
      return;
    }

    this.isResendLoading = true;
    this.authService.resendValidationEmail(this.resendForm.value.email ?? '').subscribe({
      next: (response) => {
        this.isResendLoading = false;
        this.resendSuccessMessage = response.message;
      },
      error: (err: { status?: number; error?: { message?: string } }) => {
        this.isResendLoading = false;

        if (err.status === 429) {
          this.resendErrorMessage = 'Trop de demandes depuis cette adresse IP. Réessayez dans 1 heure.';
          return;
        }

        this.resendErrorMessage = err.error?.message ?? 'Impossible de renvoyer l\'email de validation.';
      },
    });
  }

  toggleResendForm(): void {
    this.showResendForm = !this.showResendForm;
  }
}