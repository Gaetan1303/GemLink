import { PercentPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, ElementRef, computed, input, signal, viewChild } from '@angular/core';
import { MatIcon } from '@angular/material/icon';
import { PostStatus, PublicationIdentification } from '../../../core/services/post';

@Component({
  selector: 'app-post-analysis-result',
  imports: [MatIcon, PercentPipe],
  templateUrl: './post-analysis-result.html',
  styleUrl: './post-analysis-result.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PostAnalysisResult {
  readonly status = input.required<PostStatus>();
  readonly identification = input.required<PublicationIdentification | null>();

  private readonly description = viewChild<ElementRef<HTMLElement>>('description');
  protected readonly isDescriptionScrolledToEnd = signal(false);

  protected readonly isCompleted = computed(
    () => this.status() === 'ANALYZED' || this.status() === 'COMMUNITY_VALIDATED',
  );

  protected readonly isCommunityValidated = computed(() =>
    this.status() === 'COMMUNITY_VALIDATED' || this.identification()?.communityValidated === true,
  );

  protected readonly isUncertain = computed(() => {
    const result = this.identification();
    if (!result) return false;

    if (result.isUncertain !== undefined) return result.isUncertain;
    if (result.confidenceThreshold !== undefined) return result.confidence < result.confidenceThreshold;

    return !result.isHighConfidence;
  });

  protected scrollDescription(): void {
    const element = this.description()?.nativeElement;
    if (!element) return;

    const isNearBottom = element.scrollTop + element.clientHeight >= element.scrollHeight - 4;
    element.scrollTo({ top: isNearBottom ? 0 : element.scrollHeight, behavior: 'smooth' });
    this.isDescriptionScrolledToEnd.set(!isNearBottom);
  }
}
