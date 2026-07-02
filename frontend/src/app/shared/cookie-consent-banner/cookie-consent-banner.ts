import { Component } from '@angular/core';
import { CookieConsentService } from '../../core/services/cookie-consent.service';

@Component({
  selector: 'app-cookie-consent-banner',
  imports: [],
  templateUrl: './cookie-consent-banner.html',
  styleUrls: ['./cookie-consent-banner.scss'],
})
export class CookieConsentBanner {
  constructor(private cookieConsentService: CookieConsentService) {}

  accept(): void {
    this.cookieConsentService.accept();
  }

  refuse(): void {
    this.cookieConsentService.refuse();
  }
}
