import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth';
import { SharedModule } from '../../../shared/shared-module';

type ValidationState = 'loading' | 'success' | 'error';

@Component({
  selector: 'app-validate-email',
  imports: [CommonModule, RouterLink, SharedModule],
  templateUrl: './validate-email.html',
  styleUrls: ['./validate-email.scss'],
})
export class ValidateEmail implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private authService = inject(AuthService);

  state = signal<ValidationState>('loading');
  message = signal('Validation de votre email en cours...');

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token');

    if (!token) {
      this.state.set('error');
      this.message.set('Lien de validation invalide.');
      return;
    }

    this.authService.validateEmail(token).subscribe({
      next: response => {
        this.state.set('success');
        this.message.set(response.message);
        setTimeout(() => this.router.navigate(['/auth/login']), 2000);
      },
      error: error => {
        this.state.set('error');
        this.message.set(error.error?.message ?? 'Impossible de valider votre email.');
      },
    });
  }
}
