import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { Button } from './button/button';
import { Footer } from './footer/footer';
import { Header } from './header/header';
import { HeroSection } from './hero-section/hero-section';
import { HowItWorks } from './how-it-works/how-it-works';
import { KeyFigures } from './key-figures/key-figures';
import { LatestDiscoveries } from './latest-discoveries/latest-discoveries';
import { HeaderImage } from './header-image/header-image';
import { CookieConsentBanner } from './cookie-consent-banner/cookie-consent-banner';




@NgModule({
  imports: [
    CommonModule,
    Header,
    Footer,
    HeroSection,
    Button,
    HowItWorks,
    KeyFigures,
    LatestDiscoveries,
    MatIconModule,
    HeaderImage,
    CookieConsentBanner
  ],
  exports: [
    CommonModule,
    Header,
    Footer,
    HeroSection,
    Button,
    HowItWorks,
    KeyFigures,
    LatestDiscoveries,
    MatIconModule,
    HeaderImage,
    CookieConsentBanner,
  ]
})
export class SharedModule {}