import { Component, Inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon'; 
import { MatToolbarModule } from '@angular/material/toolbar';
import { MenuBurgerComponent } from '../../components/menu-burger/menu-burger';




@Component({
  selector: 'app-header',
  imports: [CommonModule, MatIconModule, MatToolbarModule, MenuBurgerComponent,],
  templateUrl: './header.html',
  styleUrls: ['./header.scss'],
})
export class HeaderComponent {}
