import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { LeaderboardEntry, LeaderboardService } from '../../core/services/leaderboard';

@Component({
  selector: 'app-leaderboard',
  imports: [CommonModule, RouterLink],
  templateUrl: './leaderboard.html',
  styleUrl: './leaderboard.scss',
})
export class Leaderboard implements OnInit {
  private readonly service = inject(LeaderboardService);

  protected readonly entries = signal<LeaderboardEntry[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.service.list().subscribe({
      next: ({ items }) => {
        this.entries.set(items);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger le classement.');
        this.loading.set(false);
      },
    });
  }
}
