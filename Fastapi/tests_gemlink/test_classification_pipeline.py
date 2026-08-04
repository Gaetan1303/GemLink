from types import SimpleNamespace
import unittest
from unittest.mock import patch

import torch
from PIL import Image

import main


class FakeDetector:
    def __init__(self, boxes):
        self.boxes = boxes
        self.confidence_argument = None
        self.image_size_argument = None

    def __call__(self, _image, *, conf, imgsz, verbose):
        self.confidence_argument = conf
        self.image_size_argument = imgsz
        assert verbose is False
        return [SimpleNamespace(boxes=self.boxes)]


def make_box(bbox, confidence):
    return SimpleNamespace(
        xyxy=torch.tensor([bbox], dtype=torch.float32),
        conf=torch.tensor([confidence], dtype=torch.float32),
    )


class ClassificationPipelineTest(unittest.TestCase):
    def test_pipeline_processes_each_detection_above_threshold(self):
        detector = FakeDetector([
            make_box([5, 5, 30, 30], 0.90),
            make_box([10, 10, 35, 35], 0.50),
            make_box([20, 20, 50, 50], 0.75),
        ])
        main.app.state.detector = detector
        main.app.state.device = "cpu"
        main.app.state.classes = ["amethyste", "quartz"]
        main.app.state.preprocess = lambda _crop: torch.zeros(3, 224, 224)
        main.app.state.classifier_model = lambda _tensor: torch.tensor([[0.0, 2.0]])

        with patch.object(main, "compute_clip_embedding", return_value=[1.0] + [0.0] * 511):
            result = main.run_vision_pipeline(Image.new("RGB", (100, 100)))

        self.assertEqual(0.5, detector.confidence_argument)
        self.assertEqual(960, detector.image_size_argument)
        self.assertEqual(2, len(result["detections"]))
        self.assertTrue(all(item["label"] == "quartz" for item in result["detections"]))
        self.assertAlmostEqual(0.90, result["detections"][0]["detector_confidence"], places=5)
        self.assertEqual(main.MODEL_VERSIONS, result["model_version"])
        self.assertEqual(result["detections"][0]["embedding"], result["embedding"])

    def test_clip_embedding_has_512_dimensions_and_unit_l2_norm(self):
        class FakeClip:
            def encode_image(self, _input):
                return torch.tensor([[3.0, 4.0] + [0.0] * 510])

        main.app.state.device = "cpu"
        main.app.state.clip_preprocess = lambda _crop: torch.zeros(3, 224, 224)
        main.app.state.clip_model = FakeClip()

        embedding = main.compute_clip_embedding(Image.new("RGB", (20, 20)))

        self.assertEqual(512, len(embedding))
        self.assertAlmostEqual(1.0, torch.linalg.vector_norm(torch.tensor(embedding)).item())

    def test_vision_response_does_not_require_knowledge_agent(self):
        vision_result = {
            "predicted_class": "quartz",
            "confidence": 0.91,
            "detector_confidence": 0.87,
            "bbox": [1, 2, 30, 40],
            "embedding": [1.0] + [0.0] * 511,
            "detections": [],
            "model_version": {"yolo": "yolo-v1"},
        }

        response = main.StoneAnalysisResponse(**main.build_vision_analysis_response(vision_result))

        self.assertEqual("quartz", response.nom)
        self.assertEqual("Non déterminé", response.categorie_geologique)
        self.assertEqual([1, 2, 30, 40], response.bbox)
