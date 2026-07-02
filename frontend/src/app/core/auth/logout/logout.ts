import { Component, inject, OnInit } from '@angular/core';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-logout',
  imports: [],
  templateUrl: './logout.html',
  styleUrls: ['./logout.scss'],
})
export class Logout implements OnInit {
  private readonly authService = inject(AuthService);

  ngOnInit(): void {
    // Appelle simplement la méthode 
    this.authService.logout(); 
  }
}