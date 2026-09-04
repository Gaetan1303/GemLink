import { PercentPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { PipelineDetection, PublicationIdentification } from '../../core/services/post';

@Component({
  selector: 'app-pipeline-result',
  imports: [PercentPipe],
  templateUrl: './pipeline-result.html',
  styleUrls: ['./pipeline-result.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PipelineResult {
  readonly result = input.required<PublicationIdentification>();

  protected readonly detections = computed<PipelineDetection[]>(() => {
    const result = this.result();
    if (result.detections?.length) return result.detections;

    return [{
      nom: result.nom,
      confidence: result.confidence,
      detectorConfidence: result.detectorConfidence ?? null,
      bbox: [0, 0, 0, 0],
    }];
  });

  protected readonly stages = computed(() => {
    const versions = this.result().modelVersion;
    const count = this.detections().length;
    return [
      { name: 'Détection', model: 'Torchvision SSDLite', version: versions?.yolo, detail: `${count} zone${count > 1 ? 's' : ''} localisée${count > 1 ? 's' : ''}` },
      { name: 'Classification', model: 'Vision Transformer', version: versions?.vit, detail: 'Scores softmax normalisés' },
      { name: 'Embedding', model: 'CLIP', version: versions?.clip, detail: 'Vecteur 512D · norme L2 = 1' },
    ];
  });

  protected confidenceLevel(confidence: number): 'high' | 'medium' | 'low' {
    if (confidence >= 0.75) return 'high';
    if (confidence >= 0.5) return 'medium';
    return 'low';
  }
}
