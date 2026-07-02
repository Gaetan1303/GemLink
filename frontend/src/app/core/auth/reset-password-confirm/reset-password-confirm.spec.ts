import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ResetPasswordConfirm } from './reset-password-confirm';

describe('ResetPasswordConfirm', () => {
  let component: ResetPasswordConfirm;
  let fixture: ComponentFixture<ResetPasswordConfirm>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ResetPasswordConfirm],
    }).compileComponents();

    fixture = TestBed.createComponent(ResetPasswordConfirm);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
