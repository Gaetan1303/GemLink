
import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../shared/shared-module';
import { KeyFigure } from '../../shared/key-figures/key-figures';
import { AuthService } from '../../core/services/auth';
import { MenuRole } from '../../components/menu-burger/menu-navigation.model';
import { CookieConsentService } from '../../core/services/cookie-consent.service';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { CookieConsentBanner } from '../../shared/cookie-consent-banner/cookie-consent-banner';

@Component({
  selector: 'app-home',
  imports: [SharedModule, CommonModule, CookieConsentBanner],
  templateUrl: './home.html',
  styleUrls: ['./home.scss'],
})
export class Home {
  private readonly authService = inject(AuthService);
  private readonly cookieConsentService = inject(CookieConsentService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  // L'observable doit émettre `true` quand le statut est `null` (non décidé)
  readonly showCookieBanner$: Observable<boolean> = this.cookieConsentService.getConsentStatus().pipe(map((status) => status === null));

  keyFiguresData: KeyFigure[] = [
    { number: '1,234', label: 'Membres inscrits' },
    { number: '5,678', label: 'Pierres identifiées' },
    { number: '890', label: 'Minéraux référencés' },
  ];
}
