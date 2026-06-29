import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Header } from '../../../shared/header/header';

@Component({
  selector: 'app-profile',
  imports: [CommonModule, Header],
  templateUrl: './profile.html',
  styleUrls: ['./profile.scss'],
})
export class Profile {}
