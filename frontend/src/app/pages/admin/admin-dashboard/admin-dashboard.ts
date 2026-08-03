import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { CommonModule, PercentPipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { timer } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { Header } from '../../../shared/header/header';
import {
  Admin,
  AdminBadge,
  AdminBadgeCondition,
  AdminDashboard,
  AdminLevel,
  AdminPointsScale,
  AdminUser,
  AdminValidationSettings,
  FineTuningJob,
  FineTuningLog,
  ModelVersion,
} from '../../../core/services/admin';
import { HeaderImage } from '../../../shared/header-image/header-image';
import { PierreSummary, ValidationService } from '../../../core/services/validation';

@Component({
  selector: 'app-admin-dashboard',
  imports: [
    CommonModule,
    PercentPipe,
    ReactiveFormsModule,
    Header,
    HeaderImage,
    MatAutocompleteModule,
    MatButtonModule,
    MatCardModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatSelectModule,
    MatTableModule,
  ],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminDashboardComponent implements OnInit {
  readonly #admin = inject(Admin);
  readonly #destroyRef = inject(DestroyRef);
  readonly #fb = inject(FormBuilder);
  readonly #validation = inject(ValidationService);
  protected readonly dashboard = signal<AdminDashboard | null>(null);
  protected readonly users = signal<AdminUser[]>([]);
  protected readonly versions = signal<ModelVersion[]>([]);
  protected readonly validationSettings = signal<AdminValidationSettings | null>(null);
  protected readonly badges = signal<AdminBadge[]>([]);
  protected readonly levels = signal<AdminLevel[]>([]);
  protected readonly mineralOptions = signal<PierreSummary[]>([]);
  protected readonly loading = signal(true);
  protected readonly working = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly trustThresholdSaved = signal(false);
  protected readonly selectedUser = signal<AdminUser | null>(null);
  protected readonly selectedLevel = signal<AdminLevel | null>(null);
  protected readonly levelPendingDeletion = signal<string | null>(null);
  protected readonly roleOptions: AdminUser['role'][] = ['user', 'expert', 'moderator', 'admin'];
  protected readonly fineTuningOverview = computed(() => {
    const dashboard = this.dashboard();
    const summary = dashboard?.fineTuning;
    return {
      availableValidations: summary?.availableValidations ?? null,
      trustScoreThreshold:
        this.validationSettings()?.datasetCandidateTrustThreshold ??
        summary?.trustScoreThreshold ??
        null,
      lastCycleAt: summary?.lastCycleAt ?? null,
    };
  });
  protected readonly hasRunningFineTuning = computed(() =>
    (this.dashboard()?.fineTuningJobs ?? []).some(
      (job) => job.status === 'pending' || job.status === 'running'
    )
  );
  protected readonly banForm = this.#fb.nonNullable.group({
    reason: ['', [Validators.required, Validators.maxLength(1000)]],
    until: [''],
  });
  protected readonly fineTuningForm = this.#fb.nonNullable.group({
    versionName: [
      '',
      [
        Validators.required,
        Validators.maxLength(50),
        Validators.pattern(/^vit-v\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/),
      ],
    ],
    minTrustScore: [70, [Validators.required, Validators.min(0), Validators.max(100)]],
  });
  protected readonly pointsForm = this.#fb.nonNullable.group({
    postCreated: [10, [Validators.required, Validators.min(0), Validators.max(10000)]],
    likeReceived: [2, [Validators.required, Validators.min(0), Validators.max(10000)]],
    validationSubmitted: [5, [Validators.required, Validators.min(0), Validators.max(10000)]],
    validationConsensusConfirmed: [
      15,
      [Validators.required, Validators.min(0), Validators.max(10000)],
    ],
  });
  protected readonly trustThresholdForm = this.#fb.nonNullable.group({
    datasetCandidateTrustThreshold: [
      70,
      [Validators.required, Validators.min(0), Validators.max(100), Validators.pattern(/^\d+$/)],
    ],
  });
  protected readonly badgeConditions: { value: AdminBadgeCondition; label: string }[] = [
    { value: 'POST_COUNT', label: 'Nombre de publications' },
    { value: 'VALIDATION_COUNT', label: 'Nombre de validations' },
    { value: 'STONE_IDENTIFICATION_COUNT', label: 'Nombre de pierres identifiées' },
    { value: 'MINERAL_IDENTIFICATION_COUNT', label: 'Identifications d’une pierre précise' },
  ];
  protected readonly badgeForm = this.#fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(1000)]],
    conditionType: ['POST_COUNT' as AdminBadgeCondition, [Validators.required]],
    conditionValue: [1, [Validators.required, Validators.min(1)]],
    mineralQuery: [''],
    pierreId: [''],
  });
  protected readonly levelForm = this.#fb.nonNullable.group({
    number: [1, [Validators.required, Validators.min(1)]],
    name: ['', [Validators.required, Validators.maxLength(50)]],
    minPoints: [0, [Validators.required, Validators.min(0)]],
    badgeId: [''],
  });

  ngOnInit(): void {
    timer(0, 5_000)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe(() => this.loadDashboard());
    this.loadUsers();
    this.loadVersions();
    this.loadPointsSettings();
    this.trustThresholdForm.valueChanges
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe(() => this.trustThresholdSaved.set(false));
    this.loadBadges();
    this.loadLevels();
    this.#validation
      .searchPierresDebounced(this.badgeForm.controls.mineralQuery.valueChanges)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe((options) => this.mineralOptions.set(options));
  }

  protected updateRole(user: AdminUser, role: AdminUser['role']): void {
    this.working.set(true);
    this.#admin
      .changeRole(user.id, role)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (updated) => this.replaceUser(updated),
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected openBan(user: AdminUser): void {
    if (user.role === 'admin') return;
    this.selectedUser.set(user);
    this.banForm.reset({ reason: '', until: '' });
  }
  protected closeBan(): void {
    this.selectedUser.set(null);
  }
  protected ban(): void {
    const user = this.selectedUser();
    this.banForm.markAllAsTouched();
    if (!user || this.banForm.invalid) return;
    this.working.set(true);
    const value = this.banForm.getRawValue();
    this.#admin
      .ban(user.id, value.reason, value.until || null)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (updated) => {
          this.replaceUser(updated);
          this.closeBan();
        },
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected unban(user: AdminUser): void {
    this.working.set(true);
    this.#admin
      .unban(user.id)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (updated) => this.replaceUser(updated),
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected startFineTuning(): void {
    this.fineTuningForm.markAllAsTouched();
    if (this.fineTuningForm.invalid) return;
    this.working.set(true);
    const value = this.fineTuningForm.getRawValue();
    this.#admin
      .startFineTuning(value.minTrustScore, value.versionName)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (job) => {
          this.dashboard.update((dashboard) =>
            dashboard
              ? {
                  ...dashboard,
                  fineTuningJobs: [
                    job,
                    ...dashboard.fineTuningJobs.filter((item) => item.id !== job.id),
                  ],
                }
              : dashboard
          );
          this.fineTuningForm.controls.versionName.reset('');
          this.loadDashboard();
          this.loadVersions();
        },
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected activate(model: ModelVersion): void {
    this.working.set(true);
    this.#admin
      .activateVit(model.id)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: () => this.loadVersions(),
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected jobModelName(job: FineTuningJob): string {
    return job.model?.name ?? job.modelVersion ?? 'Version ViT en préparation';
  }
  protected logMessage(log: FineTuningLog | string): string {
    return typeof log === 'string' ? log : log.message;
  }
  protected logLevel(log: FineTuningLog | string): string {
    return typeof log === 'string' ? 'INFO' : (log.level ?? 'INFO');
  }
  protected savePointsScale(): void {
    this.pointsForm.markAllAsTouched();
    if (this.pointsForm.invalid) return;
    this.working.set(true);
    const points: AdminPointsScale = this.pointsForm.getRawValue();
    this.#admin
      .updateValidationSettings({ points })
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (settings) => this.pointsForm.setValue(settings.points),
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected saveTrustThreshold(): void {
    this.trustThresholdForm.markAllAsTouched();
    if (this.trustThresholdForm.invalid || this.working()) return;

    this.working.set(true);
    this.error.set(null);
    this.trustThresholdSaved.set(false);
    const datasetCandidateTrustThreshold =
      this.trustThresholdForm.controls.datasetCandidateTrustThreshold.value;

    this.#admin
      .updateValidationSettings({ datasetCandidateTrustThreshold })
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (settings) => {
          this.validationSettings.set(settings);
          this.trustThresholdForm.controls.datasetCandidateTrustThreshold.setValue(
            settings.datasetCandidateTrustThreshold
          );
          this.fineTuningForm.controls.minTrustScore.setValue(
            settings.datasetCandidateTrustThreshold
          );
          this.trustThresholdSaved.set(true);
        },
        error: (error) => {
          this.showError(error);
          this.working.set(false);
        },
        complete: () => this.working.set(false),
      });
  }
  protected selectMineral(mineral: PierreSummary): void {
    this.badgeForm.patchValue(
      { mineralQuery: mineral.nom, pierreId: mineral.id },
      { emitEvent: false }
    );
    this.mineralOptions.set([]);
  }
  protected clearMineralSelection(): void {
    if (this.badgeForm.controls.pierreId.value)
      this.badgeForm.controls.pierreId.setValue('', { emitEvent: false });
  }
  protected saveBadge(): void {
    this.badgeForm.markAllAsTouched();
    const value = this.badgeForm.getRawValue();
    if (
      this.badgeForm.invalid ||
      (value.conditionType === 'MINERAL_IDENTIFICATION_COUNT' && !value.pierreId)
    ) {
      this.error.set(
        value.conditionType === 'MINERAL_IDENTIFICATION_COUNT'
          ? 'Sélectionnez la pierre concernée par ce badge.'
          : 'Les informations du badge sont invalides.'
      );
      return;
    }
    this.working.set(true);
    this.#admin
      .createBadge({
        name: value.name.trim(),
        description: value.description.trim() || null,
        conditionType: value.conditionType,
        conditionValue: value.conditionValue,
        pierreId: value.conditionType === 'MINERAL_IDENTIFICATION_COUNT' ? value.pierreId : null,
      })
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (badge) => {
          this.badges.update((items) => [badge, ...items]);
          this.badgeForm.reset({
            name: '',
            description: '',
            conditionType: 'POST_COUNT',
            conditionValue: 1,
            mineralQuery: '',
            pierreId: '',
          });
        },
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected deleteBadge(badge: AdminBadge): void {
    this.working.set(true);
    this.#admin
      .deleteBadge(badge.id)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: () => this.badges.update((items) => items.filter((item) => item.id !== badge.id)),
        error: (error) => this.showError(error),
        complete: () => this.working.set(false),
      });
  }
  protected editLevel(level: AdminLevel): void {
    this.selectedLevel.set(level);
    this.levelPendingDeletion.set(null);
    this.levelForm.setValue({
      number: level.number,
      name: level.name,
      minPoints: level.minPoints,
      badgeId: level.badgeId ?? '',
    });
  }
  protected resetLevelForm(): void {
    this.selectedLevel.set(null);
    this.levelPendingDeletion.set(null);
    const nextNumber =
      this.levels().reduce((highest, level) => Math.max(highest, level.number), 0) + 1;
    this.levelForm.reset({ number: nextNumber, name: '', minPoints: 0, badgeId: '' });
  }
  protected saveLevel(): void {
    this.levelForm.markAllAsTouched();
    if (this.levelForm.invalid || this.working()) return;
    const value = this.levelForm.getRawValue();
    const payload = {
      name: value.name.trim(),
      minPoints: value.minPoints,
      badgeId: value.badgeId || null,
    };
    const selected = this.selectedLevel();
    this.working.set(true);
    this.error.set(null);
    const request = selected
      ? this.#admin.updateLevel(selected.id, payload)
      : this.#admin.createLevel({ number: value.number, ...payload });
    request.pipe(takeUntilDestroyed(this.#destroyRef)).subscribe({
      next: (level) => {
        this.levels.update((items) =>
          [...items.filter((item) => item.id !== level.id), level].sort(
            (a, b) => a.minPoints - b.minPoints
          )
        );
        this.resetLevelForm();
      },
      error: (error) => {
        this.showError(error);
        this.working.set(false);
      },
      complete: () => this.working.set(false),
    });
  }
  protected requestLevelDeletion(level: AdminLevel): void {
    if (this.levelPendingDeletion() !== level.id) {
      this.levelPendingDeletion.set(level.id);
      return;
    }
    this.working.set(true);
    this.error.set(null);
    this.#admin
      .deleteLevel(level.id)
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: () => {
          this.levels.update((items) => items.filter((item) => item.id !== level.id));
          if (this.selectedLevel()?.id === level.id) this.resetLevelForm();
          this.levelPendingDeletion.set(null);
        },
        error: (error) => {
          this.showError(error);
          this.working.set(false);
        },
        complete: () => this.working.set(false),
      });
  }
  protected badgeName(badgeId: string | null): string {
    return this.badges().find((badge) => badge.id === badgeId)?.name ?? 'Aucun badge';
  }
  private loadDashboard(): void {
    this.#admin
      .getDashboard()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (value) => {
          this.dashboard.set(value);
          this.refreshRunningJobs(value.fineTuningJobs);
          this.loading.set(false);
        },
        error: (error) => {
          this.showError(error);
          this.loading.set(false);
        },
      });
  }
  private loadUsers(): void {
    this.#admin
      .getUsers()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (value) => this.users.set(value.items),
        error: (error) => this.showError(error),
      });
  }
  private loadVersions(): void {
    this.#admin
      .getVitVersions()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (value) => {
          this.versions.set(value);
          this.suggestNextVersion(value);
        },
        error: (error) => this.showError(error),
      });
  }
  private loadPointsSettings(): void {
    this.#admin
      .getValidationSettings()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (settings) => {
          this.validationSettings.set(settings);
          this.pointsForm.setValue(settings.points);
          this.trustThresholdForm.controls.datasetCandidateTrustThreshold.setValue(
            settings.datasetCandidateTrustThreshold
          );
          this.fineTuningForm.controls.minTrustScore.setValue(
            settings.datasetCandidateTrustThreshold
          );
        },
        error: (error) => this.showError(error),
      });
  }
  private loadBadges(): void {
    this.#admin
      .getBadges()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (badges) => this.badges.set(badges),
        error: (error) => this.showError(error),
      });
  }
  private loadLevels(): void {
    this.#admin
      .getLevels()
      .pipe(takeUntilDestroyed(this.#destroyRef))
      .subscribe({
        next: (levels) => {
          this.levels.set(levels);
          this.resetLevelForm();
        },
        error: (error) => this.showError(error),
      });
  }
  private replaceUser(updated: AdminUser): void {
    this.users.update((items) => items.map((user) => (user.id === updated.id ? updated : user)));
  }
  private refreshRunningJobs(jobs: FineTuningJob[]): void {
    for (const job of jobs) {
      if (job.status !== 'pending' && job.status !== 'running') continue;
      this.#admin
        .getFineTuningJob(job.id)
        .pipe(takeUntilDestroyed(this.#destroyRef))
        .subscribe({
          next: (updated) =>
            this.dashboard.update((dashboard) =>
              dashboard
                ? {
                    ...dashboard,
                    fineTuningJobs: dashboard.fineTuningJobs.map((item) =>
                      item.id === updated.id ? { ...item, ...updated } : item
                    ),
                  }
                : dashboard
            ),
          error: (error) => this.showError(error),
        });
    }
  }
  private suggestNextVersion(models: ModelVersion[]): void {
    if (this.fineTuningForm.controls.versionName.value !== '') return;
    const versions = models
      .map((model) => /^vit-v(\d+)\.(\d+)\.(\d+)$/.exec(model.name))
      .filter((match): match is RegExpExecArray => match !== null)
      .map((match) => [Number(match[1]), Number(match[2]), Number(match[3])] as const)
      .sort((left, right) => right[0] - left[0] || right[1] - left[1] || right[2] - left[2]);
    const latest = versions[0] ?? [1, 0, -1];
    this.fineTuningForm.controls.versionName.setValue(
      `vit-v${latest[0]}.${latest[1]}.${latest[2] + 1}`
    );
  }
  private showError(error: { error?: { message?: string } }): void {
    this.error.set(error.error?.message ?? 'Une opération d’administration a échoué.');
  }
}
