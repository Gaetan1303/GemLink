from pathlib import Path

import torch
import torch.nn as nn
from torch.utils.data import DataLoader
from torchvision import datasets, transforms

from vit import get_model

DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

DATA_DIR = Path("data/classification")
TRAIN_DIR = DATA_DIR / "train"
VAL_DIR = DATA_DIR / "val"

CHECKPOINT_DIR = Path("checkpoints")
CHECKPOINT_DIR.mkdir(parents=True, exist_ok=True)

CHECKPOINT_PATH = CHECKPOINT_DIR / "vit_stones.pth"
BATCH_SIZE = 32
EPOCHS = 20
NUM_WORKERS = 0
LR = 1e-4
IMAGE_SIZE = 224


train_transform = transforms.Compose([
    transforms.Resize((256, 256)),
    transforms.RandomResizedCrop(IMAGE_SIZE, scale=(0.8, 1.0)),
    transforms.RandomHorizontalFlip(),
    transforms.ColorJitter(
        brightness=0.2,
        contrast=0.2,
        saturation=0.2,
        hue=0.02
    ),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],
        std=[0.229, 0.224, 0.225]
    )
])

val_transform = transforms.Compose([
    transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],
        std=[0.229, 0.224, 0.225]
    )
])


def build_dataloaders():
    train_dataset = datasets.ImageFolder(TRAIN_DIR, transform=train_transform)
    val_dataset = datasets.ImageFolder(VAL_DIR, transform=val_transform)

    train_loader = DataLoader(
        train_dataset,
        batch_size=BATCH_SIZE,
        shuffle=True,
        num_workers=NUM_WORKERS
    )

    val_loader = DataLoader(
        val_dataset,
        batch_size=BATCH_SIZE,
        shuffle=False,
        num_workers=NUM_WORKERS
    )

    return train_dataset, val_dataset, train_loader, val_loader


import time

def train_one_epoch(model, loader, criterion, optimizer, device):
    model.train()

    running_loss = 0.0
    correct = 0
    total = 0

    for i, (images, labels) in enumerate(loader):

        t0 = time.time()

        images = images.to(device)
        labels = labels.to(device)

        optimizer.zero_grad()

        outputs = model(images)
        print(f"[{i}] Forward : {time.time()-t0:.2f}s")

        loss = criterion(outputs, labels)

        t1 = time.time()
        loss.backward()
        print(f"[{i}] Backward : {time.time()-t1:.2f}s")

        optimizer.step()

        running_loss += loss.item() * images.size(0)

        preds = outputs.argmax(dim=1)
        correct += (preds == labels).sum().item()
        total += labels.size(0)

        if i % 20 == 0:
            print(f"Batch {i}/{len(loader)}")

    return running_loss / total, correct / total

def validate(model, loader, criterion, device):
    model.eval()
    running_loss = 0.0
    correct = 0
    total = 0

    with torch.no_grad():
        for images, labels in loader:
            images = images.to(device)
            labels = labels.to(device)

            outputs = model(images)
            loss = criterion(outputs, labels)

            running_loss += loss.item() * images.size(0)
            preds = outputs.argmax(dim=1)
            correct += (preds == labels).sum().item()
            total += labels.size(0)

    epoch_loss = running_loss / total if total else 0.0
    epoch_acc = correct / total if total else 0.0
    return epoch_loss, epoch_acc


def main():
    print(f"Device: {DEVICE}")
    print(f"Train dir: {TRAIN_DIR}")
    print(f"Val dir: {VAL_DIR}")

    train_dataset, val_dataset, train_loader, val_loader = build_dataloaders()
    classes = train_dataset.classes

    print("Classes:", classes)
    print(f"Train samples: {len(train_dataset)}")
    print(f"Val samples: {len(val_dataset)}")

    model = get_model(num_classes=len(classes)).to(DEVICE)
    criterion = nn.CrossEntropyLoss()
    optimizer = torch.optim.AdamW(model.parameters(), lr=LR)

    best_val_acc = 0.0

    for epoch in range(EPOCHS):
        train_loss, train_acc = train_one_epoch(
            model, train_loader, criterion, optimizer, DEVICE
        )

        val_loss, val_acc = validate(
            model, val_loader, criterion, DEVICE
        )

        print(
            f"[{epoch+1}/{EPOCHS}] "
            f"train_loss={train_loss:.4f} train_acc={train_acc:.4f} "
            f"val_loss={val_loss:.4f} val_acc={val_acc:.4f}"
        )

        if val_acc > best_val_acc:
            best_val_acc = val_acc
            torch.save({
                "model": model.state_dict(),
                "classes": classes,
                "image_size": IMAGE_SIZE
            }, CHECKPOINT_PATH)
            print(f"✔ Nouveau meilleur modèle sauvegardé dans {CHECKPOINT_PATH}")

    print(f"Entraînement terminé. Best val_acc = {best_val_acc:.4f}")


if __name__ == "__main__":
    main()