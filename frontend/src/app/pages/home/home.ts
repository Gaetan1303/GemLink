import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../shared/shared-module';
import { KeyFigure } from '../../shared/key-figures/key-figures';



@Component({
  selector: 'app-home',
  imports: [SharedModule, CommonModule],
  templateUrl: './home.html',
  styleUrls: ['./home.scss'], 
})
export class Home {
  keyFiguresData: KeyFigure[] = [
    { number: '1,234', label: 'Membres inscrits' },
    { number: '5,678', label: 'Pierres identifiées' },
    { number: '890', label: 'Minéraux référencés' },
  ];
}
