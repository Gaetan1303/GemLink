import { Routes } from '@angular/router';
import { Home } from './pages/home/home';


export const routes: Routes = [
  { path: '', component: Home },
  {
    path: 'auth',
    children: [
      {
        path: 'register',
        loadComponent: () => import('./core/auth/register/register').then(m => m.Register)
      },
      {
        path: 'login',
        loadComponent: () => import('./core/auth/login/login').then(m => m.Login)
      },
      {
        path: 'validate-email/:token',
        loadComponent: () => import('./core/auth/validate-email/validate-email').then(m => m.ValidateEmail)
      },
    ]
  }
];
