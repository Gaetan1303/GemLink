import { ChangeDetectionStrategy, Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { CommonModule, PercentPipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { timer } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { Header } from '../../../shared/header/header';
import { Admin, AdminDashboard, AdminUser, ModelVersion } from '../../../core/services/admin';
import { HeaderImage } from '../../../shared/header-image/header-image';

@Component({
  selector: 'app-admin-dashboard',
  imports: [CommonModule, PercentPipe, ReactiveFormsModule, Header, HeaderImage, MatButtonModule, MatCardModule, MatFormFieldModule, MatIconModule, MatInputModule, MatProgressBarModule, MatProgressSpinnerModule, MatSelectModule, MatTableModule],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminDashboardComponent implements OnInit {
  readonly #admin = inject(Admin);
  readonly #destroyRef = inject(DestroyRef);
  readonly #fb = inject(FormBuilder);
  protected readonly dashboard = signal<AdminDashboard | null>(null);
  protected readonly users = signal<AdminUser[]>([]);
  protected readonly versions = signal<ModelVersion[]>([]);
  protected readonly loading = signal(true);
  protected readonly working = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly selectedUser = signal<AdminUser | null>(null);
  protected readonly roleOptions: AdminUser['role'][] = ['user', 'expert', 'moderator', 'admin'];
  protected readonly banForm = this.#fb.nonNullable.group({ reason: ['', [Validators.required, Validators.maxLength(1000)]], until: [''] });
  protected readonly fineTuningForm = this.#fb.nonNullable.group({ versionName: ['', [Validators.required, Validators.maxLength(50)]], minTrustScore: [70, [Validators.required, Validators.min(0), Validators.max(100)]] });

  ngOnInit(): void {
    timer(0, 10_000).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe(() => this.loadDashboard());
    this.loadUsers(); this.loadVersions();
  }

  protected updateRole(user: AdminUser, role: AdminUser['role']): void {
    this.working.set(true); this.#admin.changeRole(user.id, role).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: updated => this.replaceUser(updated), error: error => this.showError(error), complete: () => this.working.set(false) });
  }
  protected openBan(user: AdminUser): void {
    if (user.role === 'admin') return;
    this.selectedUser.set(user); this.banForm.reset({ reason: '', until: '' });
  }
  protected closeBan(): void { this.selectedUser.set(null); }
  protected ban(): void {
    const user = this.selectedUser(); this.banForm.markAllAsTouched(); if (!user || this.banForm.invalid) return;
    this.working.set(true); const value = this.banForm.getRawValue();
    this.#admin.ban(user.id, value.reason, value.until || null).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: updated => { this.replaceUser(updated); this.closeBan(); }, error: error => this.showError(error), complete: () => this.working.set(false) });
  }
  protected unban(user: AdminUser): void { this.working.set(true); this.#admin.unban(user.id).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: updated => this.replaceUser(updated), error: error => this.showError(error), complete: () => this.working.set(false) }); }
  protected startFineTuning(): void {
    this.fineTuningForm.markAllAsTouched(); if (this.fineTuningForm.invalid) return;
    this.working.set(true); const value = this.fineTuningForm.getRawValue();
    this.#admin.startFineTuning(value.minTrustScore, value.versionName).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: () => { this.fineTuningForm.reset({ versionName: '', minTrustScore: 70 }); this.loadDashboard(); this.loadVersions(); }, error: error => this.showError(error), complete: () => this.working.set(false) });
  }
  protected activate(model: ModelVersion): void { this.working.set(true); this.#admin.activateVit(model.id).pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: () => this.loadVersions(), error: error => this.showError(error), complete: () => this.working.set(false) }); }
  private loadDashboard(): void { this.#admin.getDashboard().pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: value => { this.dashboard.set(value); this.loading.set(false); }, error: error => { this.showError(error); this.loading.set(false); } }); }
  private loadUsers(): void { this.#admin.getUsers().pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: value => this.users.set(value.items), error: error => this.showError(error) }); }
  private loadVersions(): void { this.#admin.getVitVersions().pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({ next: value => this.versions.set(value), error: error => this.showError(error) }); }
  private replaceUser(updated: AdminUser): void { this.users.update(items => items.map(user => user.id === updated.id ? updated : user)); }
  private showError(error: { error?: { message?: string } }): void { this.error.set(error.error?.message ?? 'Une opération d’administration a échoué.'); }
}
