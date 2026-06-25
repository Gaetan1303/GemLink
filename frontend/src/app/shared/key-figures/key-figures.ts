import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface KeyFigure {
  number: string;
  label: string;
}

@Component({
  selector: 'app-key-figures',
  imports: [CommonModule],
  templateUrl: './key-figures.html',
  styleUrls: ['./key-figures.scss'],
})
export class KeyFigures {
  @Input() figures: KeyFigure[] = [];
}
