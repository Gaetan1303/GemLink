"""Validate a YOLO dataset and convert it to a provenance-aware COCO file."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from PIL import Image

from training.dataset_manifest import load_manifest, verify_file


def convert(dataset_root: Path, manifest_path: Path, output_path: Path) -> None:
    records = load_manifest(manifest_path)
    images: list[dict] = []
    annotations: list[dict] = []
    annotation_id = 1

    for image_id, image_path in enumerate(sorted(dataset_root.glob("images/*/*")), start=1):
        if image_path.suffix.lower() not in {".jpg", ".jpeg", ".png", ".webp"}:
            continue
        record = verify_file(image_path, dataset_root, records)
        split = image_path.parent.name
        label_path = dataset_root / "labels" / split / f"{image_path.stem}.txt"
        if not label_path.is_file():
            raise ValueError(f"missing YOLO label for {image_path}")

        with Image.open(image_path) as image:
            width, height = image.size
        images.append({
            "id": image_id,
            "file_name": image_path.relative_to(dataset_root).as_posix(),
            "width": width,
            "height": height,
            "split": split,
            "license": record.license,
            "source_url": record.url,
            "author": record.author,
            "sha256": record.sha256,
        })

        for line in label_path.read_text(encoding="utf-8").splitlines():
            values = line.split()
            if len(values) != 5:
                raise ValueError(f"invalid YOLO annotation in {label_path}")
            class_id, x_center, y_center, box_width, box_height = map(float, values)
            if int(class_id) != 0:
                raise ValueError("the GemLink detector currently supports only the stone class")
            x = (x_center - box_width / 2) * width
            y = (y_center - box_height / 2) * height
            w = box_width * width
            h = box_height * height
            annotations.append({
                "id": annotation_id,
                "image_id": image_id,
                "category_id": 1,
                "bbox": [x, y, w, h],
                "area": w * h,
                "iscrowd": 0,
            })
            annotation_id += 1

    if not images or not annotations:
        raise ValueError("no provenance-approved annotated images found")
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps({
            "images": images,
            "annotations": annotations,
            "categories": [{"id": 1, "name": "stone"}],
        }, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset-root", type=Path, required=True)
    parser.add_argument("--manifest", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    arguments = parser.parse_args()
    convert(arguments.dataset_root, arguments.manifest, arguments.output)


if __name__ == "__main__":
    main()
