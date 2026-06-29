
import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../shared/shared-module';
import { KeyFigure } from '../../shared/key-figures/key-figures';
import { NavBarMobile } from '../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../core/services/auth';
import { MenuRole } from '../../components/menu-burger/menu-navigation.model';

@Component({
  selector: 'app-home',
  imports: [SharedModule, CommonModule, NavBarMobile],
  templateUrl: './home.html',
  styleUrls: ['./home.scss'],
})
export class Home {
  readonly #auth = inject(AuthService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.#auth.currentUser()?.role ?? 'VISITEUR'
  );

  keyFiguresData: KeyFigure[] = [
    { number: '1,234', label: 'Membres inscrits' },
    { number: '5,678', label: 'Pierres identifiées' },
    { number: '890', label: 'Minéraux référencés' },
  ];
}