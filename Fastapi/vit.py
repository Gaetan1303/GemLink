import torch.nn as nn
from torchvision.models import vit_b_16, ViT_B_16_Weights


def get_model(num_classes: int):
    model = vit_b_16(weights=ViT_B_16_Weights.IMAGENET1K_V1)
    in_features = model.heads.head.in_features
    model.heads.head = nn.Linear(in_features, num_classes)
    return model