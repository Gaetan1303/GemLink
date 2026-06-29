import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Form } from '../../../form/form';
import { HeaderImage } from '../../../shared/header-image/header-image';
import { Header } from '../../../shared/header/header';
import { Footer } from '../../../shared/footer/footer';

@Component({
  selector: 'app-login',
  imports: [CommonModule, HeaderImage, Header, Form, Footer],
  templateUrl: './login.html',
  styleUrls: ['./login.scss'],
})
export class Login {}
