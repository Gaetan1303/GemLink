import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { ActivatedRoute, Router, provideRouter, convertToParamMap } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { VitrineDetail } from './vitrine-detail';
import { AuthService, User } from '../../../core/services/auth';
import { PostService } from '../../../core/services/post';
import { VitrineService, Vitrine, VitrineItem } from '../../../core/services/vitrine';

function makeVitrine(overrides: Partial<Vitrine> = {}): Vitrine {
  return {
    id: 'vitrine-1',
    title: 'Mes Améthystes',
    slug: 'mes-amethystes',
    description: 'Trouvées en Bretagne',
    status: 'DRAFT',
    viewCount: 0,
    itemsCount: 0,
    items: [],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    ...overrides,
  };
}

function makePostItem(overrides: Partial<VitrineItem> = {}): VitrineItem {
  return {
    type: 'post',
    id: 'post-1',
    position: 0,
    addedAt: new Date().toISOString(),
    publication: {
      id: 'post-1',
      title: 'Améthyste',
      mediaUrl: 'https://media.gem-link.org/x.jpg',
      mediaType: 'IMAGE',
      status: 'PUBLISHED',
    },
    ...overrides,
  };
}

function makeMediaItem(overrides: Partial<VitrineItem> = {}): VitrineItem {
  return {
    type: 'media',
    id: 'media-1',
    position: 0,
    addedAt: new Date().toISOString(),
    mediaUrl: 'https://media.gem-link.org/y.jpg',
    mediaType: 'IMAGE',
    ...overrides,
  };
}

function makeFile(name: string, type = 'image/jpeg'): File {
  return new File(['contenu'], name, { type });
}

function selectFiles(component: VitrineDetail, files: File[]): void {
  const input = { files, value: '' } as unknown as HTMLInputElement;
  component.onMediaFilesSelected({ target: input } as unknown as Event);
}

describe('VitrineDetail — US 4.1 Gestion d\'une Vitrine', () => {
  let component: VitrineDetail;
  let fixture:   ComponentFixture<VitrineDetail>;
  let router:    Router;

  let vitrineServiceMock: {
    getVitrine: ReturnType<typeof vi.fn>;
    updateVitrine: ReturnType<typeof vi.fn>;
    addItem: ReturnType<typeof vi.fn>;
    removeItem: ReturnType<typeof vi.fn>;
    addMedia: ReturnType<typeof vi.fn>;
    removeMedia: ReturnType<typeof vi.fn>;
    reorderItems: ReturnType<typeof vi.fn>;
    publish: ReturnType<typeof vi.fn>;
    unpublish: ReturnType<typeof vi.fn>;
    deleteVitrine: ReturnType<typeof vi.fn>;
  };
  let postServiceMock: { validateMediaFile: ReturnType<typeof vi.fn> };

  function configure(userValue: User | null | undefined, vitrineId = 'vitrine-1'): void {
    TestBed.configureTestingModule({
      imports: [VitrineDetail],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        provideNoopAnimations(),
        { provide: AuthService, useValue: { currentUser: signal<User | null | undefined>(userValue), isAuthenticated: () => userValue != null } },
        { provide: PostService, useValue: postServiceMock },
        { provide: VitrineService, useValue: vitrineServiceMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ id: vitrineId }) } },
        },
      ],
    });

    fixture = TestBed.createComponent(VitrineDetail);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
  }

  beforeEach(() => {
    vitrineServiceMock = {
      getVitrine: vi.fn(),
      updateVitrine: vi.fn(),
      addItem: vi.fn(),
      removeItem: vi.fn(),
      addMedia: vi.fn(),
      removeMedia: vi.fn(),
      reorderItems: vi.fn(),
      publish: vi.fn(),
      unpublish: vi.fn(),
      deleteVitrine: vi.fn(),
    };
    postServiceMock = { validateMediaFile: vi.fn().mockReturnValue(null) };
  });

  // ── Chargement ─────────────────────────────────────────────

  it('charge la Vitrine correspondant à l\'id de la route', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure(undefined);

    fixture.detectChanges();

    expect(vitrineServiceMock.getVitrine).toHaveBeenCalledWith('vitrine-1');
    expect(component['vitrine']()?.title).toBe('Mes Améthystes');
    expect(component['isLoading']()).toBe(false);
  });

  it('affiche un message dédié en cas de 404', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(throwError(() => ({ status: 404 })));
    configure(undefined);

    fixture.detectChanges();

    expect(component['loadError']()).toContain('n\'existe pas');
  });

  // ── CA-4 : canPublish ──────────────────────────────────────

  it('CA-4 : canPublish() est faux quand la Vitrine ne contient aucun élément', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 0 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });

    fixture.detectChanges();

    expect(component['canPublish']()).toBe(false);
  });

  it('CA-4 : canPublish() est vrai dès qu\'il y a au moins un élément (post ou média)', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 1, items: [makeMediaItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });

    fixture.detectChanges();

    expect(component['canPublish']()).toBe(true);
  });

  // ── CA-1 : édition ─────────────────────────────────────────

  it('CA-1 : saveEdits() met à jour la Vitrine et quitte le mode édition', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.startEditing();
    component['editForm'].patchValue({ title: 'Nouveau titre', description: 'Nouvelle description' });
    vitrineServiceMock.updateVitrine.mockReturnValue(of(makeVitrine({ title: 'Nouveau titre' })));

    component.saveEdits();

    expect(vitrineServiceMock.updateVitrine).toHaveBeenCalledWith('vitrine-1', 'Nouveau titre', 'Nouvelle description');
    expect(component['isEditing']()).toBe(false);
    expect(component['vitrine']()?.title).toBe('Nouveau titre');
  });

  // ── CA-2 : ajout d'un post existant ────────────────────────

  it('CA-2 : addItem() ajoute un post puis recharge la Vitrine', () => {
    vitrineServiceMock.getVitrine
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 0 })))
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 1, items: [makePostItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['addItemForm'].patchValue({ publicationId: 'post-1' });
    vitrineServiceMock.addItem.mockReturnValue(of(makePostItem()));

    component.addItem();

    expect(vitrineServiceMock.addItem).toHaveBeenCalledWith('vitrine-1', 'post-1');
    expect(vitrineServiceMock.getVitrine).toHaveBeenCalledTimes(2);
  });

  it('CA-2 : addItem() affiche l\'erreur serveur si le post est déjà présent', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component['addItemForm'].patchValue({ publicationId: 'post-1' });
    vitrineServiceMock.addItem.mockReturnValue(
      throwError(() => ({ error: { message: 'Ce post est déjà présent dans cette Vitrine.' } }))
    );

    component.addItem();

    expect(component['addItemError']()).toBe('Ce post est déjà présent dans cette Vitrine.');
    expect(component['isAddingItem']()).toBe(false);
  });

  // ── CA-2 (extension) : upload multiple de photos/vidéos ────

  it('CA-2 : onMediaFilesSelected() upload chaque fichier valide séquentiellement puis recharge', () => {
    vitrineServiceMock.getVitrine
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 0 })))
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 2, items: [makeMediaItem(), makeMediaItem({ id: 'media-2' })] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.addMedia.mockReturnValue(of(makeMediaItem()));

    selectFiles(component, [makeFile('pierre1.jpg'), makeFile('pierre2.jpg')]);

    expect(vitrineServiceMock.addMedia).toHaveBeenCalledTimes(2);
    expect(vitrineServiceMock.addMedia).toHaveBeenNthCalledWith(1, 'vitrine-1', expect.any(File));
    expect(vitrineServiceMock.addMedia).toHaveBeenNthCalledWith(2, 'vitrine-1', expect.any(File));
    expect(component['uploadedCount']()).toBe(2);
    expect(vitrineServiceMock.getVitrine).toHaveBeenCalledTimes(2);
    expect(component['isUploadingMedia']()).toBe(false);
  });

  it('CA-2 : onMediaFilesSelected() écarte les fichiers invalides et n\'upload que les valides', () => {
    vitrineServiceMock.getVitrine
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 0 })))
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 1, items: [makeMediaItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    postServiceMock.validateMediaFile.mockImplementation((file: File) =>
      file.type === 'application/pdf' ? 'Formats acceptés : JPEG, PNG, WebP, MP4.' : null
    );
    vitrineServiceMock.addMedia.mockReturnValue(of(makeMediaItem()));

    selectFiles(component, [makeFile('pierre.jpg'), makeFile('doc.pdf', 'application/pdf')]);

    expect(vitrineServiceMock.addMedia).toHaveBeenCalledTimes(1);
    expect(component['uploadMediaError']()).toContain('doc.pdf');
  });

  it('onMediaFilesSelected() ne fait rien si aucun fichier n\'est sélectionné', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    selectFiles(component, []);

    expect(vitrineServiceMock.addMedia).not.toHaveBeenCalled();
  });

  it('onMediaFilesSelected() recharge la Vitrine même si un des uploads échoue', () => {
    vitrineServiceMock.getVitrine
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 0 })))
      .mockReturnValueOnce(of(makeVitrine({ itemsCount: 0 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.addMedia.mockReturnValue(throwError(() => ({ status: 422 })));

    selectFiles(component, [makeFile('pierre1.jpg')]);

    expect(component['uploadMediaError']()).not.toBeNull();
    expect(component['isUploadingMedia']()).toBe(false);
    expect(vitrineServiceMock.getVitrine).toHaveBeenCalledTimes(2);
  });

  // ── Suppression d'un item (post ou média) ──────────────────

  it('removeItem() appelle removeItem du service pour un item de type "post"', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ items: [makePostItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.removeItem.mockReturnValue(of(undefined));

    component.removeItem(makePostItem());

    expect(vitrineServiceMock.removeItem).toHaveBeenCalledWith('vitrine-1', 'post-1');
    expect(vitrineServiceMock.removeMedia).not.toHaveBeenCalled();
  });

  it('removeItem() appelle removeMedia du service pour un item de type "media"', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ items: [makeMediaItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.removeMedia.mockReturnValue(of(undefined));

    component.removeItem(makeMediaItem());

    expect(vitrineServiceMock.removeMedia).toHaveBeenCalledWith('vitrine-1', 'media-1');
    expect(vitrineServiceMock.removeItem).not.toHaveBeenCalled();
  });

  // ── CA-3 : glisser-déposer ──────────────────────────────────

  it('CA-3 : onItemDrop() réordonne localement puis confirme côté serveur', () => {
    const postItem = makePostItem({ position: 0 });
    const mediaItem = makeMediaItem({ position: 1 });
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ items: [postItem, mediaItem], itemsCount: 2 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    const reorderedVitrine = makeVitrine({
      items: [{ ...mediaItem, position: 0 }, { ...postItem, position: 1 }],
      itemsCount: 2,
    });
    vitrineServiceMock.reorderItems.mockReturnValue(of(reorderedVitrine));

    component.onItemDrop({ previousIndex: 1, currentIndex: 0 } as never);

    expect(vitrineServiceMock.reorderItems).toHaveBeenCalledWith('vitrine-1', [
      { type: 'media', id: 'media-1' },
      { type: 'post', id: 'post-1' },
    ]);
    expect(component['vitrine']()?.items[0].id).toBe('media-1');
  });

  it('CA-3 : onItemDrop() recharge la Vitrine si le serveur refuse le réordonnancement', () => {
    const postItem = makePostItem({ position: 0 });
    const mediaItem = makeMediaItem({ position: 1 });
    vitrineServiceMock.getVitrine
      .mockReturnValueOnce(of(makeVitrine({ items: [postItem, mediaItem], itemsCount: 2 })))
      .mockReturnValueOnce(of(makeVitrine({ items: [postItem, mediaItem], itemsCount: 2 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.reorderItems.mockReturnValue(throwError(() => ({ status: 422 })));

    component.onItemDrop({ previousIndex: 1, currentIndex: 0 } as never);

    expect(vitrineServiceMock.getVitrine).toHaveBeenCalledTimes(2);
  });

  // ── CA-4 : confirmation puis publication ─────────────────────

  it('askPublishConfirmation() ouvre la modale de confirmation', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 1 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.askPublishConfirmation();

    expect(component['showPublishConfirm']()).toBe(true);
    expect(vitrineServiceMock.publish).not.toHaveBeenCalled();
  });

  it('cancelPublishConfirmation() ferme la modale sans publier', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 1 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.askPublishConfirmation();
    component.cancelPublishConfirmation();

    expect(component['showPublishConfirm']()).toBe(false);
    expect(vitrineServiceMock.publish).not.toHaveBeenCalled();
  });

  it('CA-4 : confirmPublish() affiche le message explicite renvoyé par le serveur si la Vitrine est vide', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 0 })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.askPublishConfirmation();
    vitrineServiceMock.publish.mockReturnValue(
      throwError(() => ({ error: { message: 'Impossible de publier une Vitrine sans aucun contenu.' } }))
    );

    component.confirmPublish();

    expect(component['publishError']()).toBe('Impossible de publier une Vitrine sans aucun contenu.');
    expect(component['isPublishing']()).toBe(false);
    expect(component['showPublishConfirm']()).toBe(true); // reste ouverte
  });

  it('confirmPublish() réussi met à jour le statut et ferme la modale', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ itemsCount: 1, items: [makeMediaItem()] })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    component.askPublishConfirmation();
    vitrineServiceMock.publish.mockReturnValue(of(makeVitrine({ status: 'PUBLISHED', itemsCount: 1 })));

    component.confirmPublish();

    expect(component['vitrine']()?.status).toBe('PUBLISHED');
    expect(component['showPublishConfirm']()).toBe(false);
  });

  it('unpublish() remet la Vitrine en brouillon sans passer par la modale', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine({ status: 'PUBLISHED' })));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.unpublish.mockReturnValue(of(makeVitrine({ status: 'DRAFT' })));

    component.unpublish();

    expect(component['vitrine']()?.status).toBe('DRAFT');
  });

  // ── Suppression de la Vitrine ────────────────────────────────

  it('deleteVitrine() redirige vers /vitrines après suppression réussie', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.deleteVitrine.mockReturnValue(of(undefined));
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.deleteVitrine();

    expect(vitrineServiceMock.deleteVitrine).toHaveBeenCalledWith('vitrine-1');
    expect(navigateSpy).toHaveBeenCalledWith(['/vitrines']);
  });

  it('deleteVitrine() affiche une erreur si la suppression échoue', () => {
    vitrineServiceMock.getVitrine.mockReturnValue(of(makeVitrine()));
    configure({ id: '1', username: 'gemuser', role: 'ROLE_USER' });
    fixture.detectChanges();

    vitrineServiceMock.deleteVitrine.mockReturnValue(
      throwError(() => ({ error: { message: 'Non autorisé.' } }))
    );

    component.deleteVitrine();

    expect(component['deleteError']()).toBe('Non autorisé.');
    expect(component['isDeleting']()).toBe(false);
    expect(component['showDeleteConfirm']()).toBe(false);
  });
});