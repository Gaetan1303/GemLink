import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { Footer } from './footer';
import { environment } from '../../../environments/environment';

describe('Footer', () => {
  let component: Footer;
  let fixture: ComponentFixture<Footer>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Footer],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Footer);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
    await fixture.whenStable();
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('devrait afficher une erreur si l\'email est invalide', () => {
    component.email.set('pas-un-email');
    component.subscribe();

    expect(component.error()).toBe('Veuillez saisir une adresse email valide.');
    httpMock.expectNone(`${environment.apiUrl}/newsletter/subscribe`);
  });

  it('devrait envoyer une requête POST avec l\'email', () => {
    component.email.set('test@gem-link.org');
    component.subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/newsletter/subscribe`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ email: 'test@gem-link.org' });

    req.flush({});
  });

  it('devrait afficher success après une réponse réussie', () => {
    component.email.set('test@gem-link.org');
    component.subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/newsletter/subscribe`);
    req.flush({});

    expect(component.success()).toBe(true);
    expect(component.email()).toBe('');
  });

  it('devrait afficher un message spécifique si email déjà inscrit (409)', () => {
    component.email.set('test@gem-link.org');
    component.subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/newsletter/subscribe`);
    req.flush('Conflict', { status: 409, statusText: 'Conflict' });

    expect(component.error()).toBe('Cette adresse est déjà inscrite à la newsletter.');
  });
});