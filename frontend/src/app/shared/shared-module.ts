import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { ButtonComponent } from './button/button';
import { FooterComponent } from './footer/footer';
import { HeaderComponent } from './header/header';
import { HeroSectionComponent } from './hero-section/hero-section';
import { HowItWorks } from './how-it-works/how-it-works';
import { KeyFigures } from './key-figures/key-figures';
import { LatestDiscoveries } from './latest-discoveries/latest-discoveries';



@NgModule({
  imports: [
    CommonModule,
    HeaderComponent,
    FooterComponent,
    HeroSectionComponent,
    ButtonComponent,
    HowItWorks,
    KeyFigures,
    LatestDiscoveries,
    MatIconModule
  ],
  exports: [
    CommonModule,
    HeaderComponent,
    FooterComponent,
    HeroSectionComponent,
    ButtonComponent,
    HowItWorks,
    KeyFigures,
    LatestDiscoveries,
    MatIconModule
  ]
})
export class SharedModule {}