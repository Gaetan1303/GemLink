import { Component, input, signal, computed } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { NAVIGATION_MENUS, MenuRole, MenuItem } from './menu-navigation.model';

@Component({
  selector: 'app-menu-burger',
  imports: [RouterLink, RouterLinkActive],
  template: `
    <div class="menu-container">
      <button
        class="burger-button"
        (click)="toggleMenu()"
        [class.open]="isOpen()"
        aria-label="Ouvrir le menu"
        [attr.aria-expanded]="isOpen()"
      >
        <span></span>
        <span></span>
        <span></span>
      </button>

      @if (isOpen()) {
        <div class="menu-overlay" (click)="closeMenu()">
          <nav class="menu-content" (click)="$event.stopPropagation()">
            <ul>
              @for (item of menuItems(); track item.route) {
                <li>
                  <a
                    [routerLink]="item.route"
                    routerLinkActive="active"
                    (click)="closeMenu()"
                  >
                    <i [class]="item.iconClass"></i>
                    {{ item.label }}
                  </a>
                </li>
              }
            </ul>
          </nav>
        </div>
      }
    </div>
  `,
  styles: [`
    .menu-container {
      position: relative;
      z-index: 1000;
    }

    /* ── Burger button ── */
    .burger-button {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      width: 28px;
      height: 20px;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 0;
    }

    .burger-button span {
      display: block;
      width: 100%;
      height: 2px;
      background-color: #c9a84c; /* gold GemLink */
      border-radius: 2px;
      transition: transform 0.3s ease, opacity 0.3s ease;
      transform-origin: center;
    }

    /* Animation vers croix */
    .burger-button.open span:nth-child(1) {
      transform: translateY(9px) rotate(45deg);
    }
    .burger-button.open span:nth-child(2) {
      opacity: 0;
      transform: scaleX(0);
    }
    .burger-button.open span:nth-child(3) {
      transform: translateY(-9px) rotate(-45deg);
    }

    /* ── Overlay ── */
    .menu-overlay {
      position: fixed;
      inset: 0;
      background: rgba(10, 14, 30, 0.7);
      backdrop-filter: blur(2px);
      animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    /* ── Drawer ── */
    .menu-content {
      position: absolute;
      top: 0;
      right: 0;
      width: 240px;
      min-height: 100vh;
      background: #0d1226;
      border-left: 1px solid rgba(201, 168, 76, 0.25);
      padding: 2rem 1.5rem;
      animation: slideIn 0.25s ease;
    }

    @keyframes slideIn {
      from { transform: translateX(100%); }
      to   { transform: translateX(0); }
    }

    ul {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      color: #e8dfc8;
      text-decoration: none;
      font-size: 0.95rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      border-radius: 4px;
      transition: background 0.2s, color 0.2s;
    }

    a:hover,
    a.active {
      background: rgba(201, 168, 76, 0.12);
      color: #c9a84c;
    }

    a.active {
      border-left: 2px solid #c9a84c;
    }
  `],
})
export class MenuBurgerComponent {
  role = input.required<MenuRole>();
  isOpen = signal<boolean>(false);
  menuItems = computed<MenuItem[]>(() => NAVIGATION_MENUS[this.role()]);

  toggleMenu(): void {
    this.isOpen.update(value => !value);
  }

  closeMenu(): void {
    this.isOpen.set(false);
  }
}