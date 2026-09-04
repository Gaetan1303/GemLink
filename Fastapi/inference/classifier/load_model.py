import torch
from models.vit import get_model


def load_model(path, device):
    checkpoint = torch.load(path, map_location=device, weights_only=True)
    classes = checkpoint["classes"]

    model = get_model(len(classes)).to(device)
    model.load_state_dict(checkpoint["model"])
    model.eval()

    return model, classes
