import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../shared/shared-module';



@Component({
  selector: 'app-home',
  imports: [SharedModule, CommonModule],
  templateUrl: './home.html',
  styleUrls: ['./home.scss'], 
})
export class Home {}
