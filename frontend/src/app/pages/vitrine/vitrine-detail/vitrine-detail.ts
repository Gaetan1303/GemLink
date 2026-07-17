import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { SharedModule } from '../../../shared/shared-module';
import { NavBarMobile } from '../../../components/nav-bar-mobile/nav-bar-mobile';
import { AuthService } from '../../../core/services/auth';
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';
import { VitrineService, Vitrine, VitrineItem } from '../../../core/services/vitrine';

// US 4.1 — Gestion d'une Vitrine : édition (CA-1), items (CA-2),
// glisser-déposer (CA-3), publication (CA-4).
@Component({
  selector: 'app-vitrine-detail',
  imports: [CommonModule, RouterLink, ReactiveFormsModule, DragDropModule, SharedModule, NavBarMobile],
  templateUrl: './vitrine-detail.html',
  styleUrls: ['./vitrine-detail.scss'],
})
export class VitrineDetail implements OnInit {

  private readonly route          = inject(ActivatedRoute);
  private readonly router         = inject(Router);
  private readonly fb             = inject(FormBuilder);
  private readonly authService    = inject(AuthService);
  private readonly vitrineService = inject(VitrineService);

  protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );

  protected readonly vitrine   = signal<Vitrine | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal<string | null>(null);

  // CA-4 : le serveur reste la source de vérité (422 si vide), mais on
  // désactive déjà le bouton côté client pour éviter l'aller-retour inutile.
  protected readonly canPublish = computed(() => (this.vitrine()?.itemsCount ?? 0) > 0);

  // L'API ne renvoie pas de champ "isOwner" explicite : pour un brouillon,
  // GET /api/vitrines/{id} répond 404 côté serveur à quiconque n'est pas
  // le propriétaire (cf. VitrineController::show) — donc si on a réussi à
  // charger la Vitrine en étant connecté, les contrôles de gestion peuvent
  // être affichés. Pour une Vitrine publiée, un visiteur connecté qui n'est
  // pas propriétaire tenterait une action qui échouerait en 403 côté
  // serveur ; c'est un compromis MVP à affiner plus tard avec un champ
  // dédié dans la réponse API si besoin.
  protected readonly isOwner = computed(
    () => this.authService.isAuthenticated() && this.vitrine() !== null
  );

  protected readonly editForm: FormGroup = this.fb.group({
    title: ['', [Validators.required, Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(500)]],
  });
  protected readonly isEditing = signal(false);
  protected readonly isSaving  = signal(false);
  protected readonly saveError = signal<string | null>(null);

  protected readonly addItemForm: FormGroup = this.fb.group({
    publicationId: ['', [Validators.required]],
  });
  protected readonly isAddingItem = signal(false);
  protected readonly addItemError = signal<string | null>(null);

  protected readonly isPublishing = signal(false);
  protected readonly publishError = signal<string | null>(null);

  protected readonly isDeleting        = signal(false);
  protected readonly showDeleteConfirm = signal(false);
  protected readonly deleteError       = signal<string | null>(null);

  private vitrineId = '';

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');

    if (!id) {
      this.loadError.set('Vitrine introuvable.');
      this.isLoading.set(false);
      return;
    }

    this.vitrineId = id;
    this.load();
  }

  private load(): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.vitrineService.getVitrine(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.editForm.patchValue({ title: vitrine.title, description: vitrine.description ?? '' });
        this.isLoading.set(false);
      },
      error: (err) => {
        this.loadError.set(
          err?.status === 404
            ? 'Cette Vitrine n\'existe pas ou n\'est plus accessible.'
            : 'Impossible de charger cette Vitrine pour le moment.'
        );
        this.isLoading.set(false);
      },
    });
  }

  // ── CA-1 : édition ────────────────────────────────────────────

  startEditing(): void {
    this.saveError.set(null);
    this.isEditing.set(true);
  }

  cancelEditing(): void {
    const current = this.vitrine();
    if (current) {
      this.editForm.patchValue({ title: current.title, description: current.description ?? '' });
    }
    this.isEditing.set(false);
  }

  saveEdits(): void {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.saveError.set(null);

    const { title, description } = this.editForm.value;

    this.vitrineService.updateVitrine(this.vitrineId, title, description ?? null).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isSaving.set(false);
        this.isEditing.set(false);
      },
      error: (err) => {
        this.isSaving.set(false);
        this.saveError.set(err?.error?.message ?? 'La mise à jour a échoué. Merci de réessayer.');
      },
    });
  }

  // ── CA-2 : items ─────────────────────────────────────────────

  addItem(): void {
    if (this.addItemForm.invalid) {
      this.addItemForm.markAllAsTouched();
      return;
    }

    this.isAddingItem.set(true);
    this.addItemError.set(null);

    const { publicationId } = this.addItemForm.value;

    this.vitrineService.addItem(this.vitrineId, String(publicationId).trim()).subscribe({
      next: () => {
        this.isAddingItem.set(false);
        this.addItemForm.reset({ publicationId: '' });
        this.load();
      },
      error: (err) => {
        this.isAddingItem.set(false);
        this.addItemError.set(err?.error?.message ?? 'Impossible d\'ajouter ce post à la Vitrine.');
      },
    });
  }

  removeItem(publicationId: string): void {
    this.vitrineService.removeItem(this.vitrineId, publicationId).subscribe({
      next: () => this.load(),
      error: (err) => {
        this.addItemError.set(err?.error?.message ?? 'La suppression de l\'item a échoué.');
      },
    });
  }

  // ── CA-3 : glisser-déposer ───────────────────────────────────

  onItemDrop(event: CdkDragDrop<VitrineItem[]>): void {
    const current = this.vitrine();
    if (!current) {
      return;
    }

    const reordered = [...current.items];
    moveItemInArray(reordered, event.previousIndex, event.currentIndex);

    // Optimiste : le nouvel ordre s'affiche immédiatement, confirmé ensuite
    // côté serveur (source de vérité des positions).
    this.vitrine.set({ ...current, items: reordered });

    const orderedPublicationIds = reordered.map((item) => item.publication.id);

    this.vitrineService.reorderItems(this.vitrineId, orderedPublicationIds).subscribe({
      next: (vitrine) => this.vitrine.set(vitrine),
      error: () => this.load(), // annule le déplacement optimiste en cas d'échec serveur
    });
  }

  // ── CA-4 : publication ────────────────────────────────────────

  publish(): void {
    this.isPublishing.set(true);
    this.publishError.set(null);

    this.vitrineService.publish(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isPublishing.set(false);
      },
      error: (err) => {
        this.isPublishing.set(false);
        // CA-4 : message explicite renvoyé par le serveur (422) affiché tel quel.
        this.publishError.set(err?.error?.message ?? 'La publication a échoué. Merci de réessayer.');
      },
    });
  }

  unpublish(): void {
    this.isPublishing.set(true);
    this.publishError.set(null);

    this.vitrineService.unpublish(this.vitrineId).subscribe({
      next: (vitrine) => {
        this.vitrine.set(vitrine);
        this.isPublishing.set(false);
      },
      error: (err) => {
        this.isPublishing.set(false);
        this.publishError.set(err?.error?.message ?? 'Une erreur est survenue. Merci de réessayer.');
      },
    });
  }

  // ── Suppression ──────────────────────────────────────────────

  confirmDelete(): void {
    this.showDeleteConfirm.set(true);
  }

  cancelDelete(): void {
    this.showDeleteConfirm.set(false);
  }

  deleteVitrine(): void {
    this.isDeleting.set(true);
    this.deleteError.set(null);

    this.vitrineService.deleteVitrine(this.vitrineId).subscribe({
      next: () => this.router.navigate(['/vitrines']),
      error: (err) => {
        this.isDeleting.set(false);
        this.showDeleteConfirm.set(false);
        this.deleteError.set(err?.error?.message ?? 'La suppression a échoué. Merci de réessayer.');
      },
    });
  }
}