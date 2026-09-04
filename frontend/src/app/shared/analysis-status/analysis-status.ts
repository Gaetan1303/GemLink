import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { animate, style, transition, trigger } from '@angular/animations';
import { PostStatus } from '../../core/services/post';

/**
 * US 3.1 — Badge animé reflétant le cycle de vie de l'analyse IA d'un post
 * (PENDING_ANALYSIS → ANALYZED | ANALYSIS_FAILED). Partagé entre post-list
 * (grille, taille compacte) et post-detail (taille normale).
 */
@Component({
  selector: 'app-analysis-status',
  imports: [CommonModule, MatIconModule],
  templateUrl: './analysis-status.html',
  styleUrls: ['./analysis-status.scss'],
  animations: [
    trigger('statusChange', [
      // Réapparition en "pop" quand l'identification aboutit.
      transition('* => ANALYZED', [
        style({ transform: 'scale(0.8)', opacity: 0 }),
        animate('420ms cubic-bezier(0.34, 1.56, 0.64, 1)', style({ transform: 'scale(1)', opacity: 1 })),
      ]),
      // Léger "shake" horizontal pour signaler l'échec sans être agressif.
      transition('* => ANALYSIS_FAILED', [
        style({ transform: 'translateX(-8px)', opacity: 0 }),
        animate('300ms ease-out', style({ transform: 'translateX(0)', opacity: 1 })),
      ]),
      transition('void => PENDING_ANALYSIS', [
        style({ opacity: 0 }),
        animate('250ms ease-out', style({ opacity: 1 })),
      ]),
    ]),
  ],
})
export class AnalysisStatus {
  @Input({ required: true }) status!: PostStatus;
  @Input() size: 'sm' | 'md' = 'md';
}
