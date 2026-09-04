import torch


def predict(model, image_tensor, classes, device, threshold=0.75, top_k=5):
    image_tensor = image_tensor.to(device)

    with torch.no_grad():
        outputs = model(image_tensor)
        probs = torch.softmax(outputs, dim=1)[0]

    top_probs, top_indices = torch.topk(probs, k=min(top_k, len(classes)))

    predictions = [
        {
            "label": classes[idx.item()],
            "confidence": float(prob.item())
        }
        for prob, idx in zip(top_probs, top_indices)
    ]

    best = predictions[0]
    final_label = best["label"] if best["confidence"] >= threshold else "unknown"

    return {
        "label": final_label,
        "confidence": best["confidence"],
        "top_k": predictions
    }