import 'zone.js'; // Included with Angular CLI.
import '@fontsource/material-icons';
import { bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app.component';
import { appConfig } from './app/app.config';

// main.ts doit déléguer TOUTE la configuration des providers à appConfig.
// L'ancienne version recréait ses propres providers (provideRouter sans HttpClient
// ni intercepteur), ce qui rendait l'intercepteur d'auth invisible à l'application.
bootstrapApplication(AppComponent, appConfig).catch((err) => console.error(err));