import torch.nn as nn
from torchvision.models import vit_b_16


def get_model(num_classes: int):
    # The GemLink checkpoint contains the complete model state. Avoid an
    # implicit ImageNet download whose training-data rights are not asserted.
    model = vit_b_16(weights=None)
    in_features = model.heads.head.in_features
    model.heads.head = nn.Linear(in_features, num_classes)
    return model
