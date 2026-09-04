"""Train GemLink's SSDLite detector from a validated COCO conversion."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

import torch
from PIL import Image
from torch.utils.data import DataLoader, Dataset
from torchvision.transforms.functional import pil_to_tensor

from inference.detector.torchvision_detector import (
    ARCHITECTURE,
    CHECKPOINT_FORMAT_VERSION,
    build_model,
)


class CocoStoneDataset(Dataset):
    def __init__(self, dataset_root: Path, annotations_path: Path, split: str) -> None:
        payload = json.loads(annotations_path.read_text(encoding="utf-8"))
        annotations_by_image: dict[int, list[dict]] = {}
        for annotation in payload["annotations"]:
            annotations_by_image.setdefault(int(annotation["image_id"]), []).append(annotation)
        self.samples = [
            (image, annotations_by_image.get(int(image["id"]), []))
            for image in payload["images"]
            if image.get("split") == split
        ]
        self.dataset_root = dataset_root

    def __len__(self) -> int:
        return len(self.samples)

    def __getitem__(self, index: int):
        metadata, annotations = self.samples[index]
        with Image.open(self.dataset_root / metadata["file_name"]) as image:
            tensor = pil_to_tensor(image.convert("RGB")).float().div(255)
        boxes = []
        for annotation in annotations:
            x, y, width, height = annotation["bbox"]
            boxes.append([x, y, x + width, y + height])
        target = {
            "boxes": torch.tensor(boxes, dtype=torch.float32),
            "labels": torch.ones(len(boxes), dtype=torch.int64),
            "image_id": torch.tensor([metadata["id"]]),
        }
        return tensor, target


def collate(batch):
    return tuple(zip(*batch, strict=True))


def train(dataset_root: Path, annotations: Path, output: Path, epochs: int) -> None:
    dataset = CocoStoneDataset(dataset_root, annotations, "train")
    if len(dataset) == 0:
        raise ValueError("the training split is empty")
    loader = DataLoader(dataset, batch_size=8, shuffle=True, num_workers=0, collate_fn=collate)
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model = build_model(num_classes=2).to(device)
    optimizer = torch.optim.AdamW(
        [parameter for parameter in model.parameters() if parameter.requires_grad],
        lr=2e-4,
        weight_decay=1e-4,
    )

    model.train()
    for epoch in range(epochs):
        for images, targets in loader:
            losses = model(
                [image.to(device) for image in images],
                [{key: value.to(device) for key, value in target.items()} for target in targets],
            )
            loss = sum(losses.values())
            optimizer.zero_grad(set_to_none=True)
            loss.backward()
            optimizer.step()
        print(f"epoch={epoch + 1}/{epochs}")

    output.parent.mkdir(parents=True, exist_ok=True)
    torch.save({
        "format_version": CHECKPOINT_FORMAT_VERSION,
        "architecture": ARCHITECTURE,
        "num_classes": 2,
        "class_names": ["stone"],
        "model": model.cpu().state_dict(),
    }, output)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset-root", type=Path, required=True)
    parser.add_argument("--annotations", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--epochs", type=int, default=30)
    arguments = parser.parse_args()
    train(arguments.dataset_root, arguments.annotations, arguments.output, arguments.epochs)


if __name__ == "__main__":
    main()
