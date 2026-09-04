import { Component, inject, OnInit } from '@angular/core';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { Header } from '../../../shared/header/header';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-logout',
  imports: [Header, MatCardModule, MatIconModule, MatProgressSpinnerModule],
  templateUrl: './logout.html',
  styleUrls: ['./logout.scss'],
})
export class Logout implements OnInit {
  private readonly authService = inject(AuthService);

  ngOnInit(): void {
    // Révoque la session serveur, efface le JWT local puis redirige vers l'accueil.
    this.authService.logout();
  }
}
