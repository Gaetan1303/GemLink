import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { VitrineList } from './vitrine-list';
import { AuthService, User } from '../../../core/services/auth';
import { VitrineService, Vitrine } from '../../../core/services/vitrine';

function makeVitrine(overrides: Partial<Vitrine> = {}): Vitrine {
  return {
    id: 'vitrine-1',
    title: 'Mes Améthystes',
    slug: 'mes-amethystes',
    description: null,
    status: 'DRAFT',
    viewCount: 0,
    itemsCount: 0,
    items: [],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    ...overrides,
  };
}

describe('VitrineList — US 4.1 Liste des Vitrines', () => {
  let component: VitrineList;
  let fixture:   ComponentFixture<VitrineList>;

  let vitrineServiceMock: { listMine: ReturnType<typeof vi.fn> };

  function configure(): void {
    TestBed.configureTestingModule({
      imports: [VitrineList],
      providers: [
        // RouterLink (utilisé dans le template) a besoin d'un routeur
        // configuré, même vide, sinon NG0201 "No provider found for
        // ActivatedRoute".
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: { currentUser: signal<User | null | undefined>(undefined) } },
        { provide: VitrineService, useValue: vitrineServiceMock },
      ],
    });

    fixture = TestBed.createComponent(VitrineList);
    component = fixture.componentInstance;
  }

  beforeEach(() => {
    vitrineServiceMock = { listMine: vi.fn() };
  });

  it('should create', () => {
    vitrineServiceMock.listMine.mockReturnValue(of({ items: [] }));
    configure();

    fixture.detectChanges();

    expect(component).toBeTruthy();
  });

  it('charge et affiche les Vitrines de l\'utilisateur', () => {
    vitrineServiceMock.listMine.mockReturnValue(of({ items: [makeVitrine(), makeVitrine({ id: 'vitrine-2' })] }));
    configure();

    fixture.detectChanges();

    expect(component['vitrines']()).toHaveLength(2);
    expect(component['isLoading']()).toBe(false);
  });

  it('affiche un message d\'erreur si le chargement échoue', () => {
    vitrineServiceMock.listMine.mockReturnValue(throwError(() => ({ status: 500 })));
    configure();

    fixture.detectChanges();

    expect(component['loadError']()).toContain('Impossible de charger');
    expect(component['isLoading']()).toBe(false);
  });

  it('n\'affiche aucune erreur et une liste vide quand l\'utilisateur n\'a encore aucune Vitrine', () => {
    vitrineServiceMock.listMine.mockReturnValue(of({ items: [] }));
    configure();

    fixture.detectChanges();

    expect(component['vitrines']()).toHaveLength(0);
    expect(component['loadError']()).toBeNull();
  });
});