from PIL import Image


def crop_with_padding(image: Image.Image, bbox, padding_ratio=0.08):
    x1, y1, x2, y2 = bbox
    width, height = image.size

    box_w = x2 - x1
    box_h = y2 - y1

    pad_x = int(box_w * padding_ratio)
    pad_y = int(box_h * padding_ratio)

    x1 = max(0, x1 - pad_x)
    y1 = max(0, y1 - pad_y)
    x2 = min(width, x2 + pad_x)
    y2 = min(height, y2 + pad_y)

    return image.crop((x1, y1, x2, y2))