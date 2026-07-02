import { isPlatformBrowser } from '@angular/common';
import { Inject, Injectable, PLATFORM_ID } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class CookieConsentService {
  private readonly storageKey = 'cookieConsent';
  // null: undecided, true: accepted, false: refused
  private _consentStatus$ = new BehaviorSubject<boolean | null>(null);
  public consentStatus$: Observable<boolean | null> = this._consentStatus$.asObservable();

  constructor(@Inject(PLATFORM_ID) private platformId: object) {
    if (isPlatformBrowser(this.platformId)) {
      const storedConsent = localStorage.getItem(this.storageKey);
      if (storedConsent) {
        this._consentStatus$.next(storedConsent === 'true');
      }
    }
  }

  public accept(): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.setItem(this.storageKey, 'true');
      this._consentStatus$.next(true);
    }
  }

  public getConsentStatus(): Observable<boolean | null> {
    return this.consentStatus$;
  }
  public refuse(): void {   
if (isPlatformBrowser(this.platformId)) {
        localStorage.setItem(this.storageKey, 'false');
        this._consentStatus$.next(false);
        }
  }

}