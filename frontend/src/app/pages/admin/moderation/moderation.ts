import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  ModerationDecision,
  ModerationReport,
  ReportReason,
  ReportService,
} from '../../../core/services/report';
import { SharedModule } from '../../../shared/shared-module';

@Component({
  selector: 'app-moderation',
  imports: [CommonModule, RouterLink, SharedModule],
  templateUrl: './moderation.html',
  styleUrl: './moderation.scss',
})
export class Moderation implements OnInit {
  private readonly service = inject(ReportService);

  protected readonly reports = signal<ModerationReport[]>([]);
  protected readonly loading = signal(true);
  protected readonly processingId = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly success = signal<string | null>(null);
  protected readonly decisionReasons = signal<Record<string, string>>({});

  ngOnInit(): void {
    this.reload();
  }

  protected decide(report: ModerationReport, decision: ModerationDecision): void {
    if (this.processingId()) return;

    const reason = this.decisionReason(report.id).trim();
    this.processingId.set(report.id);
    this.error.set(null);
    this.success.set(null);

    this.service.decide(report.id, decision, reason || undefined).subscribe({
      next: () => {
        this.reports.update(items => items.filter(item => item.id !== report.id));
        this.decisionReasons.update(reasons => {
          const { [report.id]: _removed, ...remaining } = reasons;
          return remaining;
        });
        this.success.set(decision === 'ACCEPTED'
          ? 'Le signalement a été accepté, la publication retirée et son auteur notifié.'
          : 'Le signalement a été rejeté. La publication a été restaurée si elle était masquée automatiquement.');
        this.processingId.set(null);
      },
      error: () => {
        this.error.set('La décision de modération n’a pas pu être enregistrée.');
        this.processingId.set(null);
      },
    });
  }

  protected updateDecisionReason(reportId: string, event: Event): void {
    const value = (event.target as HTMLTextAreaElement).value;
    this.decisionReasons.update(reasons => ({ ...reasons, [reportId]: value }));
  }

  protected decisionReason(reportId: string): string {
    return this.decisionReasons()[reportId] ?? '';
  }

  protected reasonLabel(reason: ReportReason): string {
    return {
      INAPPROPRIATE_CONTENT: 'Contenu inapproprié',
      WRONG_IDENTIFICATION: 'Identification incorrecte',
      SPAM: 'Spam',
      HARASSMENT: 'Harcèlement',
    }[reason];
  }

  protected actionLabel(action: string): string {
    return action === 'REPORT_ACCEPTED' ? 'Signalement accepté' : 'Signalement rejeté';
  }

  private reload(): void {
    this.loading.set(true);
    this.error.set(null);
    this.service.list().subscribe({
      next: ({ items }) => {
        this.reports.set([...items].sort((left, right) =>
          right.reportCount - left.reportCount
          || left.createdAt.localeCompare(right.createdAt),
        ));
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger les signalements.');
        this.loading.set(false);
      },
    });
  }
}
