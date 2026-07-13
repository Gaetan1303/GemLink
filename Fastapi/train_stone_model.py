import os
import time
from pathlib import Path

import torch
import torch.nn as nn
import torch.optim as optim
from torch.optim.lr_scheduler import CosineAnnealingLR
from torch.utils.data import DataLoader
from torchvision import datasets, transforms

# Réutilise EXACTEMENT la même architecture que celle chargée par main.py
# (c'est ce qui garantit que le checkpoint produit ici sera compatible en prod)
from vit import get_model

# ── CONFIGURATION DES CHEMINS ──
# Priorité : variable d'env DATASET_ROOT si définie (recommandé), sinon on essaie
# de détecter automatiquement le bon dossier Desktop.
# Sur les PC avec OneDrive, "Desktop" est souvent redirigé vers OneDrive\Desktop :
# Path.home() / "Desktop" pointe alors vers un dossier qui n'existe pas, d'où le
# FileNotFoundError. On teste donc plusieurs emplacements candidats.
def _resolve_dataset_root() -> Path:
    env_override = os.getenv("DATASET_ROOT")
    if env_override:
        return Path(env_override)

    candidats = [
        Path.home() / "Desktop" / "entrainements_model_stones",
        Path.home() / "OneDrive" / "Desktop" / "entrainements_model_stones",
        Path.home() / "OneDrive" / "Documents" / "entrainements_model_stones",
    ]
    for c in candidats:
        if (c / "train").exists() and (c / "valid").exists():
            return c

    # Rien trouvé : on lève une erreur claire plutôt qu'un FileNotFoundError cryptique
    liste = "\n".join(f"  - {c}" for c in candidats)
    raise FileNotFoundError(
        "Impossible de localiser le dossier du dataset.\n"
        f"Emplacements testés :\n{liste}\n\n"
        "Solutions :\n"
        "  1) Vérifiez que stone.py a bien été exécuté (il crée train/ et valid/).\n"
        "  2) Ou définissez explicitement le chemin :\n"
        "     set DATASET_ROOT=C:\\chemin\\vers\\entrainements_model_stones  (Windows)\n"
        "     export DATASET_ROOT=/chemin/vers/entrainements_model_stones   (Linux/Mac)"
    )


ENTRAINEMENTS_ROOT = _resolve_dataset_root()
TRAIN_DIR = ENTRAINEMENTS_ROOT / "train"
VALID_DIR = ENTRAINEMENTS_ROOT / "valid"

# Chemin de sortie : même valeur par défaut que VIT_MODEL_PATH dans main.py,
# pour ne jamais avoir à se demander "où est le fichier attendu par l'API".
OUTPUT_PATH = Path(os.getenv("VIT_MODEL_PATH", "checkpoints/vit_stones.pth"))

# ── HYPERPARAMÈTRES ──
# Le ViT pré-entraîné (ImageNet) est beaucoup plus sensible qu'un ResNet18 :
# un LR de 0.001 va "casser" les poids pré-entraînés en 1-2 époques (loss qui explose,
# accuracy qui stagne). On utilise donc un LR bas + AdamW, recette standard de
# fine-tuning des Vision Transformers.
BATCH_SIZE = 16        # ViT-B/16 est plus lourd en mémoire que ResNet18 → on réduit
EPOCHS = 15
LEARNING_RATE = 3e-5   # ~30x plus bas que pour un ResNet from scratch/fc-only
WEIGHT_DECAY = 0.01
IMAGE_SIZE = 224       # doit rester 224 : c'est la résolution attendue par vit_b_16


def main():
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(f"Entraînement configuré sur : {device}")

    # Les valeurs de Normalize doivent être identiques à celles utilisées dans
    # main.py (app.state.preprocess), sinon le modèle voit des images "décalées"
    # en prod par rapport à l'entraînement.
    data_transforms = {
        'train': transforms.Compose([
            transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
            transforms.RandomHorizontalFlip(),
            transforms.RandomRotation(15),
            transforms.ColorJitter(brightness=0.15, contrast=0.15, saturation=0.1),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
        ]),
        'valid': transforms.Compose([
            transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
        ]),
    }

    print(" Chargement des datasets locaux...")
    image_datasets = {
        'train': datasets.ImageFolder(str(TRAIN_DIR), data_transforms['train']),
        'valid': datasets.ImageFolder(str(VALID_DIR), data_transforms['valid'])
    }

    num_workers = 2 if device.type == "cpu" else 4
    dataloaders = {
        'train': DataLoader(image_datasets['train'], batch_size=BATCH_SIZE, shuffle=True, num_workers=num_workers),
        'valid': DataLoader(image_datasets['valid'], batch_size=BATCH_SIZE, shuffle=False, num_workers=num_workers)
    }

    class_names = image_datasets['train'].classes
    num_classes = len(class_names)
    print(f" Nombre de classes détectées : {num_classes}")

    # ── Construction du modèle : ViT pré-entraîné, tête remplacée pour vos classes ──
    print(" Initialisation du Vision Transformer (ViT-B/16)...")
    model = get_model(num_classes=num_classes)
    model = model.to(device)

    criterion = nn.CrossEntropyLoss(label_smoothing=0.1)
    optimizer = optim.AdamW(model.parameters(), lr=LEARNING_RATE, weight_decay=WEIGHT_DECAY)
    scheduler = CosineAnnealingLR(optimizer, T_max=EPOCHS)

    print("\n Lancement de l'entraînement...")
    since = time.time()
    best_acc = 0.0

    for epoch in range(EPOCHS):
        print(f"\n Époque {epoch + 1}/{EPOCHS}")
        print("-" * 10)

        for phase in ['train', 'valid']:
            if phase == 'train':
                model.train()
            else:
                model.eval()

            running_loss = 0.0
            running_corrects = 0

            for inputs, labels in dataloaders[phase]:
                inputs = inputs.to(device)
                labels = labels.to(device)

                optimizer.zero_grad()

                with torch.set_grad_enabled(phase == 'train'):
                    outputs = model(inputs)
                    _, preds = torch.max(outputs, 1)
                    loss = criterion(outputs, labels)

                    if phase == 'train':
                        loss.backward()
                        # Le clipping évite les à-coups de gradient typiques des ViT
                        torch.nn.utils.clip_grad_norm_(model.parameters(), max_norm=1.0)
                        optimizer.step()

                running_loss += loss.item() * inputs.size(0)
                running_corrects += torch.sum(preds == labels.data)

            epoch_loss = running_loss / len(image_datasets[phase])
            epoch_acc = running_corrects.double() / len(image_datasets[phase])

            print(f" └─ {phase.capitalize()} Loss: {epoch_loss:.4f} Acc: {epoch_acc:.4f}")

            # ── Sauvegarde au format EXACT attendu par main.py ──
            # main.py fait : checkpoint["classes"] puis checkpoint["model"]
            # Un state_dict brut (comme avant, avec ResNet18) ne fonctionnera jamais.
            if phase == 'valid' and epoch_acc > best_acc:
                best_acc = epoch_acc
                OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
                torch.save({
                    "model": model.state_dict(),
                    "classes": class_names,
                }, OUTPUT_PATH)
                print(f"Meilleur modèle sauvegardé → {OUTPUT_PATH}")

        scheduler.step()

    time_elapsed = time.time() - since
    print(f"\n Entraînement terminé en {time_elapsed // 60:.0f}m {time_elapsed % 60:.0f}s")
    print(f" Meilleure précision en validation : {best_acc:.4f}")
    print(f" Checkpoint final : {OUTPUT_PATH.resolve()}")


if __name__ == '__main__':
    main()