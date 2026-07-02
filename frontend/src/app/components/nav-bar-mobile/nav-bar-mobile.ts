import { Component, computed, inject, input } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { MatRippleModule } from '@angular/material/core';


import { NAVIGATION_MENUS, MenuRole, MenuItem } from '../menu-burger/menu-navigation.model';

@Component({
  selector: 'app-nav-bar-mobile',
  imports: [RouterLink, RouterLinkActive, MatIconModule, MatRippleModule],
  templateUrl: './nav-bar-mobile.html',
  styleUrls: ['./nav-bar-mobile.scss'],
})
export class NavBarMobile {

  // ── Inputs ──────────────────────────────────────────────────
  role = input.required<MenuRole>();

  // ── State ──────────────────────────────────────────────────
  readonly #router = inject(Router);

  menuItems = computed<MenuItem[]>(() => NAVIGATION_MENUS[this.role()].navItems);
  // ── Helpers ────────────────────────────────────────────────
  isActive(route: string): boolean {
    return this.#router.isActive(route, {
      paths:              route === '/' ? 'exact' : 'subset',
      queryParams:        'ignored',
      fragment:           'ignored',
      matrixParams:       'ignored',
    });
  }
}