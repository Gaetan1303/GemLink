import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { SharedModule } from '../../shared/shared-module';
import { CurrentLeaderboardUser, LeaderboardEntry, LeaderboardService } from '../../core/services/leaderboard';



@Component({
  selector: 'app-leaderboard',
  imports: [CommonModule, RouterLink, SharedModule,],
  templateUrl: './leaderboard.html',
  styleUrl: './leaderboard.scss',
})
export class Leaderboard implements OnInit {
  private readonly service = inject(LeaderboardService);

  protected readonly entries = signal<LeaderboardEntry[]>([]);
  protected readonly total = signal(0);
  protected readonly currentUser = signal<CurrentLeaderboardUser | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.service.list().subscribe({
      next: ({ items, total, currentUser }) => {
        this.entries.set(items);
        this.total.set(total);
        this.currentUser.set(currentUser);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger le classement.');
        this.loading.set(false);
      },
    });
  }
}
