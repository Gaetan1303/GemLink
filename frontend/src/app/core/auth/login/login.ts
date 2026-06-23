import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Form } from '../../../form/form';
import { SharedModule } from '../../../shared/shared-module';



@Component({
  selector: 'app-login',
  imports: [CommonModule, SharedModule, Form],
  templateUrl: './login.html',
  styleUrls: ['./login.scss'],
})
export class Login {}
