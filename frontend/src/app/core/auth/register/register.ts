import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RegisterForm } from '../../../form/register-form/register-form';
import { SharedModule } from '../../../shared/shared-module';



@Component({
  selector: 'app-register',
  imports: [CommonModule, RegisterForm, SharedModule],
  templateUrl: './register.html',
  styleUrls: ['./register.scss'],
})
export class Register {}
