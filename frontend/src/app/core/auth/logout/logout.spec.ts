import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Logout } from './logout';
import { AuthService } from '../../services/auth';

describe('Logout', () => {
  let component: Logout;
  let fixture: ComponentFixture<Logout>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Logout],
      providers: [{ provide: AuthService, useValue: { logout: () => undefined, currentUser: () => null } }],
    }).compileComponents();

    fixture = TestBed.createComponent(Logout);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
