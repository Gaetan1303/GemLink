import { ComponentFixture, TestBed } from '@angular/core/testing';

import { HttpClientTestingModule } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { MenuBurger } from './menu-burger';

describe('MenuBurger', () => {
  let component: MenuBurger;
  let fixture: ComponentFixture<MenuBurger>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MenuBurger, HttpClientTestingModule],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(MenuBurger);
    component = fixture.componentInstance;
    // L'input `role` est requis
    fixture.componentRef.setInput('role', 'VISITEUR');
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
