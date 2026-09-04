import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { PierreSummary, Validation, ValidationAction, ValidationService } from '../../core/services/validation';

/**
 * US 2.7 CA-1 — Sous chaque post analysé : Confirmer / Corriger / Invalider
 * l'identification IA. Corriger ouvre un champ texte avec autocomplétion
 * sur les minéraux connus (le champ accepte du texte libre : la valeur
 * proposée n'est pas contrainte à une suggestion existante,
 * l'autocomplétion n'est qu'une aide de saisie).
 *
 * Une seule validation par (post, utilisateur) côté serveur : ce composant
 * charge le choix précédent au démarrage (getMine) pour refléter l'état
 * réellement enregistré plutôt que de repartir à zéro à chaque visite.
 */
@Component({
  selector: 'app-validation-widget',
  imports: [CommonModule, ReactiveFormsModule, MatAutocompleteModule, MatFormFieldModule, MatInputModule, MatIconModule],
  templateUrl: './validation-widget.html',
  styleUrls: ['./validation-widget.scss'],
})
export class ValidationWidget implements OnInit {

  readonly #validationService = inject(ValidationService);

  postId = input.required<string>();

  protected readonly myValidation = signal<Validation | null>(null);
  protected readonly isLoading    = signal(true);
  protected readonly isSubmitting = signal(false);
  protected readonly submitError  = signal<string | null>(null);

  // CA-1 : le champ de correction ne s'affiche que lorsque l'utilisateur a
  // cliqué sur "Corriger" — évite d'encombrer l'UI par défaut.
  protected readonly isCorrecting = signal(false);
  protected readonly proposedLabelControl = new FormControl('', { nonNullable: true });
  protected readonly suggestions = signal<PierreSummary[]>([]);

  protected readonly currentAction = computed<ValidationAction | null>(() => this.myValidation()?.action ?? null);

  ngOnInit(): void {
    this.#validationService.getMine(this.postId()).subscribe({
      next: (validation) => {
        this.myValidation.set(validation);
        this.isLoading.set(false);
      },
      error: () => this.isLoading.set(false),
    });

    this.#validationService.searchPierresDebounced(this.proposedLabelControl.valueChanges)
      .subscribe((results) => this.suggestions.set(results));
  }

  protected confirm(): void {
    this.submit('CONFIRM');
  }

  protected reject(): void {
    this.submit('REJECT');
  }

  protected startCorrecting(): void {
    this.isCorrecting.set(true);
    this.proposedLabelControl.setValue(this.myValidation()?.proposedLabel ?? '');
  }

  protected cancelCorrecting(): void {
    this.isCorrecting.set(false);
    this.submitError.set(null);
  }

  protected submitCorrection(): void {
    const label = this.proposedLabelControl.value.trim();

    if (label === '') {
      this.submitError.set('Merci de préciser le label proposé.');
      return;
    }

    this.submit('CORRECT', label);
  }

  private submit(action: ValidationAction, proposedLabel?: string): void {
    this.isSubmitting.set(true);
    this.submitError.set(null);

    this.#validationService.submitValidation(this.postId(), action, proposedLabel).subscribe({
      next: (validation) => {
        this.myValidation.set(validation);
        this.isSubmitting.set(false);
        this.isCorrecting.set(false);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.submitError.set(err?.error?.message ?? 'La validation a échoué. Merci de réessayer.');
      },
    });
  }
}
