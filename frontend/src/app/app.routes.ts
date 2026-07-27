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
// US 2.1 — Publication d'un post MVP
  // US 2.2 — Consultation des posts (liste + détail), publiques
  {
    path: 'posts',
    loadComponent: () => import('./pages/post/post-list/post-list').then(m => m.PostList)
  },
  {
    path: 'posts/new',
    loadComponent: () => import('./pages/post/post-create/post-create').then(m => m.PostCreate)
  },
  {
    path: 'posts/:id',
    loadComponent: () => import('./pages/post/post-detail/post-detail').then(m => m.PostDetail)
  },
  {
    path: 'vitrine',
    loadComponent: () => import('./pages/vitrine/vitrine-list/vitrine-list').then(m => m.VitrineList)
  },
  {
    path: 'vitrines/new',
    loadComponent: () => import('./pages/vitrine/vitrine-create/vitrine-create').then(m => m.VitrineCreate)
  },
  // US 4.2 - CA-1 : page publique par slug, sans authentification.
  // Doit être déclarée AVANT 'vitrines/:id' (owner, par UUID) — sinon le
  // router matcherait 'public' comme une valeur de :id et ne l'atteindrait
  // jamais, exactement comme 'vitrines/new' doit précéder 'vitrines/:id'.
  {
    path: 'vitrines/public/:slug',
    loadComponent: () => import('./pages/vitrine/vitrine-public/vitrine-public').then(m => m.VitrinePublic)
  },
  {
    path: 'vitrines/:id',
    loadComponent: () => import('./pages/vitrine/vitrine-detail/vitrine-detail').then(m => m.VitrineDetail)
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
