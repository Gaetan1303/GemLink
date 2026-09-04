import { Component, input } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';

@Component({
  selector: 'app-button',
  standalone: true,
  imports: [MatIconModule, MatToolbarModule, MatButtonModule],
  templateUrl: './button.html',
  styleUrls: ['./button.scss'],
})
export class Button {
  label = input<string>();
  disabled = input<boolean>(false);
  type = input<'button' | 'submit' | 'reset'>('button'); // pour la soumission de formulaire
}
