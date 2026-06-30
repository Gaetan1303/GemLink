import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { Rgpd } from './rgpd';
import { environment } from '../../../../environments/environment';

describe('Rgpd', () => {
  let component: Rgpd;
  let fixture: ComponentFixture<Rgpd>;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Rgpd],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Rgpd);
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

  it('devrait afficher une erreur si les champs obligatoires sont vides', () => {
    component.submit();
    fixture.detectChanges();

    expect(component.error()).toBe('Veuillez remplir tous les champs obligatoires.');
    expect(component.success()).toBe(false);
  });

  it('ne devrait pas envoyer de requête si le formulaire est incomplet', () => {
    component.nom.set('Billy');
    component.submit();

    httpMock.expectNone(`${environment.apiUrl}/rgpd-request`);
  });

  it('devrait envoyer une requête POST avec les bonnes données', () => {
    component.nom.set('Billy');
    component.email.set('billy@gem-link.org');
    component.message.set('Je souhaite accéder à mes données personnelles.');

    component.submit();

    const req = httpMock.expectOne(`${environment.apiUrl}/rgpd-request`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      nom: 'Billy',
      email: 'billy@gem-link.org',
      message: 'Je souhaite accéder à mes données personnelles.',
    });

    req.flush({});
  });

  it('devrait passer isLoading à true pendant la requête', () => {
    component.nom.set('Billy');
    component.email.set('billy@gem-link.org');
    component.message.set('Demande de suppression');

    component.submit();

    expect(component.isLoading()).toBe(true);

    const req = httpMock.expectOne(`${environment.apiUrl}/rgpd-request`);
    req.flush({});

    expect(component.isLoading()).toBe(false);
  });

  it('devrait passer success à true après une réponse réussie', () => {
    component.nom.set('Billy');
    component.email.set('billy@gem-link.org');
    component.message.set('Demande de portabilité');

    component.submit();

    const req = httpMock.expectOne(`${environment.apiUrl}/rgpd-request`);
    req.flush({});

    expect(component.success()).toBe(true);
    expect(component.error()).toBe('');
  });

  it('devrait afficher une erreur si la requête échoue', () => {
    component.nom.set('Billy');
    component.email.set('billy@gem-link.org');
    component.message.set('Demande de rectification');

    component.submit();

    const req = httpMock.expectOne(`${environment.apiUrl}/rgpd-request`);
    req.flush('Erreur serveur', { status: 500, statusText: 'Internal Server Error' });

    expect(component.error()).toBe('Une erreur est survenue. Veuillez réessayer.');
    expect(component.isLoading()).toBe(false);
    expect(component.success()).toBe(false);
  });
});