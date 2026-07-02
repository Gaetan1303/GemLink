import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { Profile } from './pages/user/profile/profile';


export const routes: Routes = [
  { path: '', component: Home },
  {
    path: 'mentions-legales',
    loadComponent: () => import('./pages/home/mentions-legales/mentions-legales').then(m => m.MentionsLegales)
  },
  {
    path: 'presse',
    loadComponent: () => import('./pages/home/presse/presse').then(m => m.Presse)
  },
  {
    path: 'contact',
    loadComponent: () => import('./pages/home/contact/contact').then(m => m.Contact)
  },
  {
    path: 'politique-de-confidentialite',
    loadComponent: () => import('./pages/home/politique-de-confidentialite/politique-de-confidentialite').then(m => m.PolitiqueDeConfidentialite)
  },
  {
    path: 'rgpd',
    loadComponent: () => import('./pages/home/rgpd/rgpd').then(m => m.Rgpd)
  },

  {
    path: 'users/me',
    loadComponent: () => import('./pages/user/profile/profile').then(m => m.Profile)
  },

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
      {
        path: 'logout',
        loadComponent: () => import('./core/auth/logout/logout').then(m => m.Logout)
      },
            // US 1.6 — Étape 1 : formulaire de demande de réinitialisation (CA-1)
      {
        path: 'reset-password',
        loadComponent: () => import('./core/auth/reset-password/reset-password').then(m => m.ResetPassword)
      },
      // US 1.6 — Étape 2 : formulaire de saisie du nouveau mot de passe (CA-2, CA-3, CA-4)
      {
        path: 'reset-password/:token',
        loadComponent: () => import('./core/auth/reset-password-confirm/reset-password-confirm').then(m => m.ResetPasswordConfirm)
      }
    ]
  }
];
