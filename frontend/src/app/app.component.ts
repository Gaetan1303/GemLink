import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AuthService } from './core/services/auth';
import { NavBarMobile } from './components/nav-bar-mobile/nav-bar-mobile';
import { MenuBurger } from './components/menu-burger/menu-burger';
import { MenuRole } from './components/menu-burger/menu-navigation.model';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet,],
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  protected readonly title = signal('GemLink');

  readonly auth = inject(AuthService);

  protected readonly currentRole = computed<MenuRole>(() => {
    const user = this.auth.currentUser();
    if (user === undefined) return 'VISITEUR'; 
    return user?.role ?? 'VISITEUR';
  });
}