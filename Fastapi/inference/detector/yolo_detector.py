from pathlib import Path
from ultralytics import YOLO


class StoneDetector:
    def __init__(self, model_path: str):
        self.model_path = Path(model_path)
        self.model = None

        if self.model_path.exists():
            self.model = YOLO(str(self.model_path))
        else:
            print(
                f"[WARN] Modèle YOLO introuvable: {self.model_path}. "
                f"La détection sera désactivée tant que tu n'auras pas entraîné/copier yolo_stone.pt."
            )

    def detect(self, image, conf=0.25, iou=0.45):
        if self.model is None:
            return None, 0.0

        results = self.model.predict(
            source=image,
            conf=conf,
            iou=iou,
            verbose=False
        )

        detections = []

        for result in results:
            if result.boxes is None:
                continue

            for box in result.boxes:
                xyxy = box.xyxy[0].tolist()
                score = float(box.conf[0].item())

                detections.append({
                    "bbox": [int(x) for x in xyxy],
                    "confidence": score
                })

        if not detections:
            return None, 0.0

        best = max(detections, key=lambda d: d["confidence"])
        return best["bbox"], best["confidence"]