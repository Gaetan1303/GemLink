import os
import random
import shutil

def split_dataset(source_dir, train_dir, valid_dir, split_ratio=0.2):
    """
    Prend les images de source_dir et les distribue proprement 
    entre train_dir (80%) et valid_dir (20%).
    """
    # 1. Lister toutes les images téléchargées
    images = [f for f in os.listdir(source_dir) if f.lower().endswith(('.png', '.jpg', '.jpeg'))]
    
    # 2. Mélanger la liste de manière aléatoire
    random.shuffle(images)
    
    # 3. Calculer l'index de coupure
    split_index = int(len(images) * split_ratio)
    valid_images = images[:split_index]
    train_images = images[split_index:]
    
    # 4. Créer les dossiers s'ils n'existent pas
    os.makedirs(train_dir, exist_ok=True)
    os.makedirs(valid_dir, exist_ok=True)
    
    # 5. Déplacer (ou copier) les fichiers
    for img in train_images:
        shutil.move(os.path.join(source_dir, img), os.path.join(train_dir, img))
        
    for img in valid_images:
        shutil.move(os.path.join(source_dir, img), os.path.join(valid_dir, img))

    print(f"Fini ! {len(train_images)} images en Train et {len(valid_images)} images en Valid.")

# Exemple d'utilisation :
# split_dataset("path/to/raw_amethysts", "path/to/dataset/train/amethyst", "path/to/dataset/valid/amethyst")