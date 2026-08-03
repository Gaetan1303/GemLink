import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth';

/** Réserve le dashboard aux modérateurs et aux administrateurs. */
export const moderationGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const role = auth.currentUser()?.role;

  return role === 'ROLE_MODERATOR' || role === 'ROLE_ADMIN'
    ? true
    : router.createUrlTree(['/auth/login']);
};
