import tempfile
import unittest
from pathlib import Path
from unittest.mock import Mock, patch

import torch

from inference.detector import DetectorNotReadyError, TorchvisionDetector


class TorchvisionDetectorTest(unittest.TestCase):
    def test_missing_checkpoint_is_reported_as_not_ready(self):
        with self.assertRaisesRegex(DetectorNotReadyError, "DETECTOR_NOT_READY"):
            TorchvisionDetector("/path/that/does/not/exist.pth")

    def test_yolo_checkpoint_is_not_silently_loaded(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "legacy-yolo.pt"
            torch.save({"model": {}}, path)
            with self.assertRaisesRegex(DetectorNotReadyError, "incompatible checkpoint format"):
                TorchvisionDetector(path)

    def test_compatible_metadata_builds_the_declared_architecture(self):
        fake_model = Mock()
        fake_model.to.return_value = fake_model
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "detector.pth"
            torch.save({
                "format_version": 1,
                "architecture": "ssdlite320_mobilenet_v3_large",
                "num_classes": 2,
                "class_names": ["stone"],
                "model": {"weight": torch.tensor([1.0])},
            }, path)
            with patch("inference.detector.torchvision_detector.build_model", return_value=fake_model):
                detector = TorchvisionDetector(path)

        fake_model.load_state_dict.assert_called_once()
        fake_model.eval.assert_called_once()
        self.assertIs(fake_model, detector.model)
