import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ModerationReport, ReportService } from '../../../core/services/report';

@Component({
  selector: 'app-moderation',
  imports: [CommonModule, RouterLink],
  templateUrl: './moderation.html',
  styleUrl: './moderation.scss',
})
export class Moderation implements OnInit {
  private readonly service = inject(ReportService);

  protected readonly reports = signal<ModerationReport[]>([]);
  protected readonly loading = signal(true);
  protected readonly processingId = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.reload();
  }

  protected decide(report: ModerationReport, decision: 'ACCEPTED' | 'REJECTED'): void {
    if (this.processingId()) return;
    this.processingId.set(report.id);
    this.error.set(null);
    this.service.decide(report.id, decision).subscribe({
      next: () => {
        this.reports.update(items => items.filter(item => item.id !== report.id));
        this.processingId.set(null);
      },
      error: () => {
        this.error.set('La décision de modération n’a pas pu être enregistrée.');
        this.processingId.set(null);
      },
    });
  }

  private reload(): void {
    this.loading.set(true);
    this.service.list().subscribe({
      next: ({ items }) => {
        this.reports.set(items);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger les signalements.');
        this.loading.set(false);
      },
    });
  }
}
