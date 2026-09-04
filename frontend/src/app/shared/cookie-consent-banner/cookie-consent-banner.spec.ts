import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CookieConsentBanner } from './cookie-consent-banner';

describe('CookieConsentBanner', () => {
  let component: CookieConsentBanner;
  let fixture: ComponentFixture<CookieConsentBanner>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CookieConsentBanner],
    }).compileComponents();

    fixture = TestBed.createComponent(CookieConsentBanner);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
