import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpClientTestingModule } from '@angular/common/http/testing';

import { NavBarMobile } from './nav-bar-mobile';

describe('NavBarMobile', () => {
  let component: NavBarMobile;
  let fixture: ComponentFixture<NavBarMobile>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NavBarMobile, HttpClientTestingModule],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(NavBarMobile);
    component = fixture.componentInstance;

    // Input obligatoire
    fixture.componentRef.setInput('role', 'VISITEUR');

    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('devrait afficher les items du rôle visiteur', () => {
    fixture.detectChanges();
    const links = fixture.nativeElement.querySelectorAll('.nav-bar-mobile__link');
    expect(links.length).toBe(3); // Connexion + Inscription + Accueil
  });

  it('devrait afficher les items du rôle user', async () => {
    fixture.componentRef.setInput('role', 'ROLE_USER');
    fixture.detectChanges();
    const links = fixture.nativeElement.querySelectorAll('.nav-bar-mobile__link');
    expect(links.length).toBe(5); // Maquette figma avec Home, Post, Identifier, Profil, Galerie
  });

  it('devrait afficher les items du rôle admin', async () => {
    fixture.componentRef.setInput('role', 'ROLE_ADMIN');
    fixture.detectChanges();
    const links = fixture.nativeElement.querySelectorAll('.nav-bar-mobile__link');
    expect(links.length).toBe(4); // Dashboard, Modération, Retour
  });
});