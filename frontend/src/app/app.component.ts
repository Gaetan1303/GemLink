import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AuthService } from './core/services/auth';
import { NavBarMobile } from './components/nav-bar-mobile/nav-bar-mobile';
import { MenuRole } from './components/menu-burger/menu-navigation.model';
import { Title, Meta } from '@angular/platform-browser';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, NavBarMobile],
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  private readonly titleService = inject(Title);
  private readonly metaService = inject(Meta);

  protected readonly title = signal('GemLink — Identification de pierres et minéraux par une communauté passionnée');

  readonly auth = inject(AuthService);

  protected readonly currentRole = computed<MenuRole>(() => {
    const user = this.auth.currentUser();
    if (user === undefined) return 'VISITEUR';
    return user?.role ?? 'VISITEUR';
  });

  constructor() {
    this.titleService.setTitle(this.title());
    this.metaService.addTag({ name: 'description', content: 'GemLink: Identifiez, explorez et partagez le monde fascinant des pierres et minéraux grâce à l\'intelligence artificielle. Votre guide personnel en géologie.' });
    this.metaService.addTag({ property: 'og:image', content: 'https://gem-link.org/assets/og-image.jpg' }); // Placeholder, user should provide actual image URL
    this.metaService.addTag({ rel: 'canonical', href: 'https://gem-link.org/' });
  }
}