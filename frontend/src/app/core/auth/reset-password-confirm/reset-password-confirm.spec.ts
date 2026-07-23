import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { ResetPasswordConfirm } from './reset-password-confirm';
import { AuthService } from '../../services/auth'; // À adapter selon ton arborescence

describe('ResetPasswordConfirm', () => {
  let component: ResetPasswordConfirm;
  let fixture: ComponentFixture<ResetPasswordConfirm>;

  // ── Le mock doit exposer "resetPassword" ──
  const authServiceMock = {
    resetPassword: vi.fn(), 
    currentUser: signal(null),
  };

  const activatedRouteMock = {
    snapshot: {
      paramMap: {
        get: vi.fn().mockReturnValue('abc123hash'),
      },
    },
  };

  beforeEach(async () => {
    vi.resetAllMocks();

    await TestBed.configureTestingModule({
      imports: [ResetPasswordConfirm],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authServiceMock },
        { provide: ActivatedRoute, useValue: activatedRouteMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ResetPasswordConfirm);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should call resetPassword and handle success', () => {
    authServiceMock.resetPassword.mockReturnValue(of(undefined));

    if (component.form) {
      component.form.get('password')?.setValue('NewValidPassword123!');
      component.form.get('confirmPassword')?.setValue('NewValidPassword123!');
      component.onSubmit();
    } else {
      component.onSubmit();
    }

    // L'assertion doit aussi utiliser "resetPassword" !
    expect(authServiceMock.resetPassword).toHaveBeenCalled();
  });
});