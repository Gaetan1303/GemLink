import { signal } from '@angular/core'; // ◄ Ajouté pour le Header
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { provideRouter, Router } from '@angular/router'; // ◄ Ajout de provideRouter
import { of, throwError } from 'rxjs';
import { vi, type MockInstance } from 'vitest';

import { Form } from './form';
import { AuthService } from '../core/services/auth';
import { SharedModule } from '../shared/shared-module';

// ── Helpers ──────────────────────────────────────────────────
const VALID_EMAIL    = 'pierre@gmail.com';
const VALID_PASSWORD = 'Password1!';

function fillAndSubmit(fixture: ComponentFixture<Form>, email: string, passwordHash: string): void {
  fixture.componentInstance.loginForm.setValue({ email, passwordHash });
  fixture.componentInstance.onSubmit();
  fixture.detectChanges();
}

// ── Suite ────────────────────────────────────────────────────
describe('Form — US 1.3 Connexion', () => {

  let fixture:     ComponentFixture<Form>;
  let component:   Form;
  let loginSpy:    MockInstance;
  let navigateSpy: MockInstance;

  let authServiceMock: Record<string, unknown>;

  beforeEach(async () => {
    loginSpy = vi.fn();

    authServiceMock = {
      login:         loginSpy,
      register:      vi.fn(),
      validateEmail: vi.fn(),
      currentUser:   signal(null), // ◄ Sécurise le Header globalement
    };

    await TestBed.configureTestingModule({
      imports: [Form, ReactiveFormsModule, SharedModule],
      providers: [
        provideRouter([]), // ◄ Règle l'erreur ActivatedRoute de manière propre
        { provide: AuthService, useValue: authServiceMock },
      ],
    }).compileComponents();

    fixture   = TestBed.createComponent(Form);
    component = fixture.componentInstance;

    // Récupération de l'instance réelle du routeur générée par provideRouter
    const router = TestBed.inject(Router);
    navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    fixture.detectChanges();
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
  });

  // ── Création ─────────────────────────────────────────────
  it('devrait créer le composant', () => {
    expect(component).toBeTruthy();
  });

  it('devrait initialiser le formulaire vide', () => {
    expect(component.loginForm.get('email')?.value).toBe('');
    expect(component.loginForm.get('passwordHash')?.value).toBe('');
  });

  // ── Validation locale ────────────────────────────────────
  it('devrait afficher les erreurs si soumis vide', () => {
    component.onSubmit();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="email-error"]')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('[data-testid="password-error"]')).toBeTruthy();
    expect(loginSpy).not.toHaveBeenCalled();
  });

  it('devrait rejeter un email mal formaté', () => {
    fillAndSubmit(fixture, 'pas-un-email', VALID_PASSWORD);
    expect(component.emailCtrl.invalid).toBe(true);
    expect(loginSpy).not.toHaveBeenCalled();
  });

  // ── CA-1 ─────────────────────────────────────────────────
  it('CA-1 — devrait naviguer vers "/" après connexion réussie', () => {
    loginSpy.mockReturnValue(of({ token: 'jwt-test' }));

    fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);

    expect(loginSpy).toHaveBeenCalledOnce();
    expect(loginSpy).toHaveBeenCalledWith({
      email:    VALID_EMAIL,
      passwordHash: VALID_PASSWORD,
    });
    expect(navigateSpy).toHaveBeenCalledWith(['/']);
  });

  // ── CA-2 ─────────────────────────────────────────────────
  it('CA-2 — devrait afficher un message générique sur erreur 401', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 401 })));

    fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);

    const el = fixture.nativeElement.querySelector('[data-testid="server-error"]');
    expect(el).toBeTruthy();
    expect(el.textContent.trim()).toBe('Identifiants incorrects.');
  });

  it('CA-2 — devrait afficher le même message sur erreur 404', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 404 })));

    fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);

    const el = fixture.nativeElement.querySelector('[data-testid="server-error"]');
    expect(el.textContent.trim()).toBe('Identifiants incorrects.');
  });

  // ── CA-3 ─────────────────────────────────────────────────
  it('CA-3 — devrait activer le throttle après 5 échecs', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 401 })));

    for (let i = 0; i < 5; i++) {
      fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);
    }

    expect(component.isThrottled()).toBe(true);
    expect(component.throttleRemainingSeconds()).toBe(30);
    expect(fixture.nativeElement.querySelector('[data-testid="throttle-error"]')).toBeTruthy();
  });

  it('CA-3 — ne devrait pas appeler AuthService si throttle actif', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 401 })));

    for (let i = 0; i < 5; i++) {
      fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);
    }

    const countBefore = loginSpy.mock.calls.length;
    component.onSubmit();
    expect(loginSpy.mock.calls.length).toBe(countBefore);
  });

  it('CA-3 — devrait lever le throttle après 30 secondes', async () => {
    vi.useFakeTimers();

    loginSpy.mockReturnValue(throwError(() => ({ status: 401 })));

    for (let i = 0; i < 5; i++) {
      fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);
    }

    expect(component.isThrottled()).toBe(true);

    // Avance les timers de 30 secondes
    await vi.advanceTimersByTimeAsync(30_000);
    fixture.detectChanges();

    expect(component.isThrottled()).toBe(false);
  });

  it('CA-3 — devrait throttler immédiatement sur réponse 429', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 429 })));

    fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);

    expect(component.isThrottled()).toBe(true);
  });

  it('CA-3 — devrait réinitialiser les tentatives après succès', () => {
    loginSpy.mockReturnValue(throwError(() => ({ status: 401 })));
    for (let i = 0; i < 4; i++) {
      fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);
    }

    loginSpy.mockReturnValue(of({ token: 'jwt-test' }));
    fillAndSubmit(fixture, VALID_EMAIL, VALID_PASSWORD);

    expect(component.isThrottled()).toBe(false);
  });
});
