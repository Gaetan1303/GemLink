import torch.nn as nn
from torchvision.models import vit_b_16


def get_model(num_classes: int):
    model = vit_b_16(weights=None)
    in_features = model.heads.head.in_features
    model.heads.head = nn.Linear(in_features, num_classes)
    return model
