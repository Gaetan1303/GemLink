import { Component, computed, inject } from '@angular/core';
import {SharedModule} from "../../../shared/shared-module";
import {AuthService} from "../../../core/services/auth";
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';

@Component({
  selector: 'app-presse',
  imports: [SharedModule,],
  templateUrl: './presse.html',
  styleUrls: ['./presse.scss'],
})


export class Presse {
    private readonly authService = inject(AuthService);
    protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );
  
   readonly mediaKit: MediaKit[] = [
    { label: 'Logo GemLink — fond sombre',  format: 'PNG', size: '42 Ko',  url: '/assets/press/logo-dark.png' },
    { label: 'Logo GemLink — fond clair',   format: 'PNG', size: '38 Ko',  url: '/assets/press/logo-light.png' },
    { label: 'Logo GemLink — vectoriel',    format: 'SVG', size: '12 Ko',  url: '/assets/press/logo.svg' },
    { label: 'Captures d\'écran app',       format: 'ZIP', size: '4,2 Mo', url: '/assets/press/screenshots.zip' },
    { label: 'Texte officiel de présentation', format: 'PDF', size: '180 Ko', url: '/assets/press/gemlink-presentation.pdf' },
  ];

  readonly mediaCoverage: MediaCoverage[] = [
    // les parutions 
  ];
  readonly keyFigures = [
    { number: '1 234', label: 'Membres inscrits' },
    { number: '5 678', label: 'Pierres identifiées' },
    { number: '890',   label: 'Minéraux référencés' },
  ];
}

interface MediaCoverage {
  media: string;
  title: string;
  date: string;
  url: string;
}

interface MediaKit {
  label: string;
  format: string;
  size: string;
  url: string;
}



 
