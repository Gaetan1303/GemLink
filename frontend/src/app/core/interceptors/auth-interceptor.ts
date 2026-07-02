import {
  HttpErrorResponse,
  HttpInterceptorFn,
  HttpRequest,
  HttpHandlerFn,
  HttpEvent,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import {
  BehaviorSubject,
  catchError,
  filter,
  Observable,
  switchMap,
  take,
  throwError,
} from 'rxjs';
import { AuthService } from '../services/auth';
import { environment } from '../../../environments/environment';

// ── État partagé du processus de rafraîchissement ────────────────────────────
//
// Ces variables sont au niveau module (singleton) : elles survivent à tous les
// appels de l'intercepteur, ce qui est exactement le comportement voulu pour
// sérialiser les tentatives de refresh concurrentes.

/** Vrai pendant la durée d'un appel /auth/refresh en cours. */
let isRefreshing = false;

/**
 * File d'attente des requêtes bloquées pendant le refresh.
 *
 * - Émet `null`       → refresh en cours, les requêtes attendent.
 * - Émet `string`     → refresh terminé, les requêtes reprennent avec le nouveau token.
 * - Émet `false`      → refresh échoué, les requêtes sont abandonnées.
 */
const refreshSubject$ = new BehaviorSubject<string | null | false>(null);

// ── URL de l'endpoint de refresh (exclue de l'interception pour éviter la boucle infinie) ──

const REFRESH_URL = `${environment.apiUrl}/auth/refresh`;
const LOGOUT_URL  = `${environment.apiUrl}/auth/logout`;

// ── Intercepteur ─────────────────────────────────────────────────────────────

/**
 * CA-1 : ajoute automatiquement le JWT à chaque requête sortante et,
 *        en cas de 401, tente un refresh silencieux puis rejoue la requête.
 * CA-3 : si le refresh échoue, nettoie la session et redirige vers /auth/login.
 */
export const authInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
): Observable<HttpEvent<unknown>> => {
  const authService = inject(AuthService);
  const router      = inject(Router);

  // Ne jamais intercepter les appels au refresh lui-même (boucle infinie)
  // ni les appels au logout (pas de retry utile)
  if (isAuthBypassUrl(req.url)) {
    return next(req);
  }

  const token = localStorage.getItem('token');
  const authedReq = token ? attachToken(req, token) : req;

  return next(authedReq).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401) {
        return handleUnauthorized(authedReq, next, authService, router);
      }
      return throwError(() => error);
    }),
  );
};

// ── Gestion du 401 ───────────────────────────────────────────────────────────

function handleUnauthorized(
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
  authService: AuthService,
  router: Router,
): Observable<HttpEvent<unknown>> {
  if (!isRefreshing) {
    // CA-1 : lancer le refresh et sérialiser les requêtes en attente
    isRefreshing = true;
    refreshSubject$.next(null); // signal "refresh en cours"

    return authService.refresh().pipe(
      switchMap((newToken: string) => {
        isRefreshing = false;
        refreshSubject$.next(newToken); // débloquer les requêtes en attente
        return next(attachToken(req, newToken));
      }),
      catchError((err: unknown) => {
        isRefreshing = false;
        refreshSubject$.next(false); // signal "refresh échoué"

        // CA-3 : session définitivement invalide → déconnexion propre + redirection
        authService.clearSession();
        router.navigate(['/auth/login']);

        return throwError(() => err);
      }),
    );
  }

  // CA-1 : une requête concurrente est déjà en train de rafraîchir le token —
  // on attend le résultat puis on rejoue avec le nouveau token (ou on abandonne).
  return refreshSubject$.pipe(
    filter((token): token is string | false => token !== null),
    take(1),
    switchMap((token) => {
      if (token === false) {
        // Le refresh a échoué pour une autre requête concurrente
        return throwError(() => new Error('Session expirée.'));
      }
      return next(attachToken(req, token));
    }),
  );
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function attachToken(
  req: HttpRequest<unknown>,
  token: string,
): HttpRequest<unknown> {
  return req.clone({
    setHeaders: { Authorization: `Bearer ${token}` },
  });
}

function isAuthBypassUrl(url: string): boolean {
  return url === REFRESH_URL || url === LOGOUT_URL;
}