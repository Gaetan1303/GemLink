import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { Router, provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { VitrineCreate } from './vitrine-create';
import { AuthService, User } from '../../../core/services/auth';
import { PostService } from '../../../core/services/post';
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

function makeFile(name: string, type = 'image/jpeg'): File {
  return new File(['contenu'], name, { type });
}

function selectFiles(component: VitrineCreate, files: File[]): void {
  const input = { files, value: '' } as unknown as HTMLInputElement;
  component.onFilesSelected({ target: input } as unknown as Event);
}

// US 4.1 CA-1/CA-2 — Création d'une Vitrine avec upload multiple,
// confirmation de création, puis confirmation de publication.
describe('VitrineCreate', () => {
  let component: VitrineCreate;
  let fixture:   ComponentFixture<VitrineCreate>;
  let router:    Router;

  let currentUser: ReturnType<typeof signal<User | null | undefined>>;
  let vitrineServiceMock: {
    createVitrine: ReturnType<typeof vi.fn>;
    addMedia: ReturnType<typeof vi.fn>;
    getVitrine: ReturnType<typeof vi.fn>;
    publish: ReturnType<typeof vi.fn>;
  };
  let postServiceMock: { validateMediaFile: ReturnType<typeof vi.fn> };

  beforeAll(() => {
    // jsdom n'implémente pas createObjectURL/revokeObjectURL nativement.
    URL.createObjectURL = vi.fn(() => 'blob:mock-url');
    URL.revokeObjectURL = vi.fn();
  });

  function configure(userValue: User | null | undefined): void {
    currentUser = signal<User | null | undefined>(userValue);

    TestBed.configureTestingModule({
      imports: [VitrineCreate],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser, isAuthenticated: () => userValue != null } },
        { provide: PostService, useValue: postServiceMock },
        { provide: VitrineService, useValue: vitrineServiceMock },
      ],
    });

    fixture = TestBed.createComponent(VitrineCreate);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
  }

  beforeEach(() => {
    vitrineServiceMock = {
      createVitrine: vi.fn(),
      addMedia: vi.fn(),
      getVitrine: vi.fn(),
      publish: vi.fn(),
    };
    postServiceMock = { validateMediaFile: vi.fn().mockReturnValue(null) };
  });

  it('redirige vers /auth/login si l\'utilisateur n\'est pas authentifié', () => {
    currentUser = signal<User | null | undefined>(null);

    TestBed.configureTestingModule({
      imports: [VitrineCreate],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser, isAuthenticated: () => false } },
        { provide: PostService, useValue: postServiceMock },
        { provide: VitrineService, useValue: vitrineServiceMock },
      ],
    });

    router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    fixture = TestBed.createComponent(VitrineCreate);
    component = fixture.componentInstance;

    expect(navigateSpy).toHaveBeenCalledWith(['/auth/login']);
  });

  it('CA-1 : submit() refuse un titre vide', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: '' });
    component.submit();

    expect(vitrineServiceMock.createVitrine).not.toHaveBeenCalled();
    expect(component['vitrineForm'].get('title')?.touched).toBe(true);
  });

  // ── CA-2 : sélection multiple de fichiers ───────────────────

  it('CA-2 : onFilesSelected() accumule les fichiers valides et rejette les invalides', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    postServiceMock.validateMediaFile.mockImplementation((file: File) =>
      file.type === 'application/pdf' ? 'Formats acceptés : JPEG, PNG, WebP, MP4.' : null
    );

    selectFiles(component, [
      makeFile('pierre1.jpg'),
      makeFile('document.pdf', 'application/pdf'),
      makeFile('pierre2.jpg'),
    ]);

    expect(component['selectedFiles']()).toHaveLength(2);
    expect(component['fileErrors']()).toHaveLength(1);
    expect(component['fileErrors']()[0]).toContain('document.pdf');
  });

  it('CA-2 : plusieurs appels à onFilesSelected() cumulent les fichiers déjà sélectionnés', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    selectFiles(component, [makeFile('pierre1.jpg')]);
    selectFiles(component, [makeFile('pierre2.jpg')]);

    expect(component['selectedFiles']()).toHaveLength(2);
  });

  it('removeSelectedFile() retire un fichier de la sélection', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    selectFiles(component, [makeFile('pierre1.jpg'), makeFile('pierre2.jpg')]);
    component.removeSelectedFile(0);

    expect(component['selectedFiles']()).toHaveLength(1);
    expect(component['selectedFiles']()[0].file.name).toBe('pierre2.jpg');
  });

  // ── submit() : création + upload séquentiel ─────────────────

  it('submit() sans fichier : crée la Vitrine et affiche directement la confirmation', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 0 })));

    component.submit();

    expect(vitrineServiceMock.addMedia).not.toHaveBeenCalled();
    expect(component['createdVitrine']()?.id).toBe('vitrine-42');
    expect(component['isSubmitting']()).toBe(false);
  });

  it('submit() avec fichiers : upload chaque fichier séquentiellement puis affiche la confirmation', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    selectFiles(component, [makeFile('pierre1.jpg'), makeFile('pierre2.jpg')]);

    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.addMedia.mockReturnValue(of({ type: 'media', id: 'media-x' }));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 2 })));

    component.submit();

    expect(vitrineServiceMock.addMedia).toHaveBeenCalledTimes(2);
    expect(vitrineServiceMock.addMedia).toHaveBeenNthCalledWith(1, 'vitrine-42', expect.any(File));
    expect(vitrineServiceMock.addMedia).toHaveBeenNthCalledWith(2, 'vitrine-42', expect.any(File));
    expect(component['uploadedCount']()).toBe(2);
    expect(component['createdVitrine']()?.itemsCount).toBe(2);
    expect(component['selectedFiles']()).toHaveLength(0); // nettoyée après finalisation
  });

  it('submit() affiche une erreur de création sans lancer d\'upload', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    selectFiles(component, [makeFile('pierre1.jpg')]);
    vitrineServiceMock.createVitrine.mockReturnValue(
      throwError(() => ({ error: { message: 'Le titre est obligatoire.' } }))
    );

    component.submit();

    expect(vitrineServiceMock.addMedia).not.toHaveBeenCalled();
    expect(component['submitError']()).toBe('Le titre est obligatoire.');
    expect(component['isSubmitting']()).toBe(false);
  });

  it('submit() affiche un message d\'avertissement si un upload échoue, sans bloquer la confirmation', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    selectFiles(component, [makeFile('pierre1.jpg')]);

    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.addMedia.mockReturnValue(throwError(() => ({ status: 422 })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 0 })));

    component.submit();

    expect(component['submitError']()).toContain('n\'ont pas pu être ajoutées');
    expect(component['createdVitrine']()?.id).toBe('vitrine-42');
  });

  // ── Écran de confirmation de création ────────────────────────

  it('viewVitrine() redirige vers le détail sans publier', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    component.submit();

    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    component.viewVitrine();

    expect(vitrineServiceMock.publish).not.toHaveBeenCalled();
    expect(navigateSpy).toHaveBeenCalledWith(['/vitrines', 'vitrine-42']);
  });

  // ── Deuxième confirmation : publication ──────────────────────

  it('askPublishConfirmation() ouvre la modale de confirmation', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    component.submit();

    component.askPublishConfirmation();

    expect(component['showPublishConfirm']()).toBe(true);
  });

  it('cancelPublishConfirmation() ferme la modale sans publier', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42' })));
    component.submit();
    component.askPublishConfirmation();

    component.cancelPublishConfirmation();

    expect(component['showPublishConfirm']()).toBe(false);
    expect(vitrineServiceMock.publish).not.toHaveBeenCalled();
  });

  it('confirmPublish() réussi publie puis redirige vers le détail', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 1 })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 1 })));
    component.submit();
    component.askPublishConfirmation();

    vitrineServiceMock.publish.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', status: 'PUBLISHED' })));
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.confirmPublish();

    expect(vitrineServiceMock.publish).toHaveBeenCalledWith('vitrine-42');
    expect(navigateSpy).toHaveBeenCalledWith(['/vitrines', 'vitrine-42']);
    expect(component['showPublishConfirm']()).toBe(false);
  });

  it('CA-4 : confirmPublish() affiche le message explicite si la Vitrine est vide', () => {
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['vitrineForm'].patchValue({ title: 'Mes Améthystes' });
    vitrineServiceMock.createVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 0 })));
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ id: 'vitrine-42', itemsCount: 0 })));
    component.submit();
    component.askPublishConfirmation();

    vitrineServiceMock.publish.mockReturnValue(
      throwError(() => ({ error: { message: 'Impossible de publier une Vitrine sans aucun contenu.' } }))
    );
    const navigateSpy = vi.spyOn(router, 'navigate');
    navigateSpy.mockClear();

    component.confirmPublish();

    expect(component['publishError']()).toBe('Impossible de publier une Vitrine sans aucun contenu.');
    expect(component['showPublishConfirm']()).toBe(true); // reste ouverte pour laisser l'utilisateur réagir
    expect(navigateSpy).not.toHaveBeenCalled();
  });
});