from inference.detector.interface import Detection, DetectorInterface, DetectorNotReadyError
from inference.detector.torchvision_detector import TorchvisionDetector

__all__ = [
    "Detection",
    "DetectorInterface",
    "DetectorNotReadyError",
    "TorchvisionDetector",
]
