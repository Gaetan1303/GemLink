from pathlib import Path
from typing import Any

import torch
from PIL import Image
from torchvision.models.detection import ssdlite320_mobilenet_v3_large
from torchvision.transforms.functional import pil_to_tensor

from inference.detector.interface import Detection, DetectorInterface, DetectorNotReadyError


CHECKPOINT_FORMAT_VERSION = 1
ARCHITECTURE = "ssdlite320_mobilenet_v3_large"


def build_model(num_classes: int):
    # Never download COCO or ImageNet weights implicitly. Production checkpoints
    # must have their own reviewed provenance and contain the complete state dict.
    return ssdlite320_mobilenet_v3_large(
        weights=None,
        weights_backbone=None,
        num_classes=num_classes,
    )


class TorchvisionDetector(DetectorInterface):
    def __init__(self, checkpoint_path: str | Path, device: str = "cpu") -> None:
        self.checkpoint_path = Path(checkpoint_path)
        self.device = device
        self.model = self._load_checkpoint()

    def _load_checkpoint(self):
        if not self.checkpoint_path.is_file():
            raise DetectorNotReadyError("DETECTOR_NOT_READY")

        checkpoint: Any = torch.load(
            self.checkpoint_path,
            map_location=self.device,
            weights_only=True,
        )
        if not isinstance(checkpoint, dict):
            raise DetectorNotReadyError("DETECTOR_NOT_READY: invalid checkpoint container")
        if checkpoint.get("format_version") != CHECKPOINT_FORMAT_VERSION:
            raise DetectorNotReadyError("DETECTOR_NOT_READY: incompatible checkpoint format")
        if checkpoint.get("architecture") != ARCHITECTURE:
            raise DetectorNotReadyError("DETECTOR_NOT_READY: incompatible architecture")
        if "model" not in checkpoint or not isinstance(checkpoint.get("class_names"), list):
            raise DetectorNotReadyError("DETECTOR_NOT_READY: missing checkpoint metadata")

        num_classes = int(checkpoint.get("num_classes", 0))
        if num_classes < 2 or num_classes != len(checkpoint["class_names"]) + 1:
            raise DetectorNotReadyError("DETECTOR_NOT_READY: invalid class count")

        model = build_model(num_classes)
        model.load_state_dict(checkpoint["model"], strict=True)
        model.to(self.device)
        model.eval()
        return model

    def detect(self, image: Image.Image, confidence_threshold: float) -> list[Detection]:
        tensor = pil_to_tensor(image.convert("RGB")).float().div(255).to(self.device)
        with torch.inference_mode():
            prediction = self.model([tensor])[0]

        detections = []
        for box, score in zip(prediction["boxes"], prediction["scores"], strict=True):
            confidence = float(score.detach().cpu().item())
            if confidence <= confidence_threshold:
                continue
            detections.append(
                Detection(
                    bbox=[float(value) for value in box.detach().cpu().tolist()],
                    confidence=confidence,
                )
            )
        return detections
