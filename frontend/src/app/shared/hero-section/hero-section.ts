import { Component } from '@angular/core';
import { MatIconModule } from '@angular/material/icon'; 
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { Button } from "../button/button";



@Component({
  selector: 'app-hero-section',
  imports: [MatIconModule, MatToolbarModule, MatButtonModule, ],
  templateUrl: './hero-section.html',
  styleUrls: ['./hero-section.scss'],
})
export class HeroSection {}
