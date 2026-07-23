import { ApplicationConfig, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';

import { routes } from './app.routes';
import { authInterceptor } from './core/interceptors/auth-interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    // US 1.4 : withInterceptors enregistre l'intercepteur fonctionnel qui :
    // - ajoute automatiquement le JWT à chaque requête (Authorization: Bearer)
    // - relance un refresh silencieux en cas de 401
    // - redirige vers /auth/login si le refresh échoue (CA-3)
    provideHttpClient(withInterceptors([authInterceptor])),
    // US 3.1 : requis pour tout trigger `animations: [...]` (badge de statut,
    // révélation de la fiche minéralogique). Sans ce provider, Angular
    // n'exécute AUCUNE animation déclarée dans un @Component — c'est la
    // cause exacte du "l'animation ne se lance pas".
    provideAnimationsAsync(),
  ],
};