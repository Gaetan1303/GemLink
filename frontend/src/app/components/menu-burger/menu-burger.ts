import { Component, computed, input, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { NgClass } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { NAVIGATION_MENUS, MenuRole, MenuItem } from './menu-navigation.model';

@Component({
  selector: 'app-menu-burger',
  imports: [RouterLink, RouterLinkActive, MatIconModule],
  templateUrl: './menu-burger.html',
  styleUrls: ['./menu-burger.scss'],
})
export class MenuBurger {

  // ── Inputs ──────────────────────────────────────────────────
  role = input.required<MenuRole>();

  // ── State ───────────────────────────────────────────────────
  isOpen = signal(false);

menuItems = computed<MenuItem[]>(() => NAVIGATION_MENUS[this.role()].menuItems);

  // ── Actions ─────────────────────────────────────────────────
  toggleMenu(): void {
    this.isOpen.update(v => !v);
  }

  closeMenu(): void {
    this.isOpen.set(false);
  }
}