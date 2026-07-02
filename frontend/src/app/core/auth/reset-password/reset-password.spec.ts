import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { ResetPassword } from './reset-password';
import { AuthService } from '../../services/auth'; // À adapter selon ton arborescence

describe('ResetPassword', () => {
  let component: ResetPassword;
  let fixture: ComponentFixture<ResetPassword>;

  // ── Le mock doit exposer "requestPasswordReset" ──
  const authServiceMock = {
    requestPasswordReset: vi.fn(),
    currentUser: signal(null),
  };

  beforeEach(async () => {
    vi.resetAllMocks();

    await TestBed.configureTestingModule({
      imports: [ResetPassword],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ResetPassword);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize with an invalid form', () => {
    expect(component.form.valid).toBe(false);
    expect(component.emailCtrl.errors?.['required']).toBe(true);
  });

  it('should validate email format', () => {
    component.emailCtrl.setValue('invalid-email');
    expect(component.emailCtrl.errors?.['email']).toBe(true);

    component.emailCtrl.setValue('pierre@gmail.com');
    expect(component.form.valid).toBe(true);
  });

  it('should call requestPasswordReset and show success screen on success (CA-1)', () => {
    authServiceMock.requestPasswordReset.mockReturnValue(of(undefined));

    component.emailCtrl.setValue('pierre@gmail.com');
    component.onSubmit();

    // L'assertion doit aussi utiliser "requestPasswordReset" !
    expect(authServiceMock.requestPasswordReset).toHaveBeenCalledWith('pierre@gmail.com');
    expect(component.successShown()).toBe(true);
  });

  it('should STILL show success screen even if API returns an error (Anti-énumération CA-1)', () => {
    authServiceMock.requestPasswordReset.mockReturnValue(
      throwError(() => new Error('User not found'))
    );

    component.emailCtrl.setValue('inconnu@gmail.com');
    component.onSubmit();

    // L'assertion doit aussi utiliser "requestPasswordReset" !
    expect(authServiceMock.requestPasswordReset).toHaveBeenCalledWith('inconnu@gmail.com');
    expect(component.successShown()).toBe(true); 
  });
});