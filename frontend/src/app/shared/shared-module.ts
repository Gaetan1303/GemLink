import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { ButtonComponent } from './button/button';
import { FooterComponent } from './footer/footer';
import { HeaderComponent } from './header/header';
import { HeroSectionComponent } from './hero-section/hero-section';


@NgModule({
  imports: [
    CommonModule,
    HeaderComponent,
    FooterComponent,
    HeroSectionComponent,
    ButtonComponent,
    MatIconModule
  ],
  exports: [
    CommonModule,
    HeaderComponent,
    FooterComponent,
    HeroSectionComponent,
    ButtonComponent,
    MatIconModule
  ]
})
export class SharedModule {}