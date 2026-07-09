from pathlib import Path

from datasets import load_dataset
from PIL import Image

print("Chargement du dataset...")
dataset = load_dataset("Nech-C/mineralimage5K-98")

ROOT = Path("data/detection")

for split in ["train", "val", "test"]:
    (ROOT / "images" / split).mkdir(parents=True, exist_ok=True)
    (ROOT / "labels" / split).mkdir(parents=True, exist_ok=True)

CLASS_ID = 0

SPLIT_MAP = {
    "train": "train",
    "validation": "val",
    "test": "test",
}

for hf_split, yolo_split in SPLIT_MAP.items():

    print(f"\nTraitement {hf_split}")

    split = dataset[hf_split]

    for idx, sample in enumerate(split):

        image: Image.Image = sample["image"]

        image_name = f"{idx:06d}.jpg"

        image_path = ROOT / "images" / yolo_split / image_name
        label_path = ROOT / "labels" / yolo_split / image_name.replace(".jpg", ".txt")

        image.save(image_path)

        with open(label_path, "w") as f:

            for mineral in sample["mineral_boxes"]:

                xmin, ymin, xmax, ymax = mineral["box"]

                # Les coordonnées sont DÉJÀ normalisées !
                x_center = (xmin + xmax) / 2
                y_center = (ymin + ymax) / 2

                width = xmax - xmin
                height = ymax - ymin

                f.write(
                    f"{CLASS_ID} "
                    f"{x_center:.6f} "
                    f"{y_center:.6f} "
                    f"{width:.6f} "
                    f"{height:.6f}\n"
                )

        if idx % 500 == 0:
            print(f"{idx}/{len(split)}")

print("\nDataset YOLO généré.")