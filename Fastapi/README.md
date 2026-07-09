# StoneVision API

> High-performance REST API for gemstone, semi-precious stone and mineral recognition using **FastAPI**, **YOLOv8** and **Vision Transformer (ViT)**.

StoneVision API is a Computer Vision project written in Python that detects and classifies minerals from an image.

The project combines two deep learning models:

- **YOLOv8** for mineral localization (object detection)
- **Vision Transformer (ViT)** for mineral classification

The API is built with **FastAPI**, taking advantage of automatic OpenAPI documentation, request validation with Pydantic and high performance asynchronous endpoints. The architecture and API layer were designed by following the philosophy and best practices presented in the official FastAPI project. :contentReference[oaicite:1]{index=1}

---

# Features

- Fast REST API
- Automatic Swagger documentation
- Automatic ReDoc documentation
- Mineral detection using YOLOv8
- Mineral classification using Vision Transformer
- Image upload
- Confidence score
- Bounding box coordinates
- Top-K predictions
- Easy model replacement
- CPU and CUDA support

---

# Architecture

```text
                    Image
                      │
                      ▼
            ┌─────────────────┐
            │     FastAPI      │
            └─────────────────┘
                      │
                      ▼
            ┌─────────────────┐
            │     YOLOv8       │
            │ Detect the stone │
            └─────────────────┘
                      │
                 Crop mineral
                      │
                      ▼
            ┌─────────────────┐
            │ VisionTransformer│
            │ Classify stone   │
            └─────────────────┘
                      │
                      ▼
               JSON Response
```

---

# Project structure

```text
.
├── checkpoints/
│   ├── vit_stones.pth
│   └── yolo_stone.pt
│
├── data/
│   ├── classification/
│   └── detection/
│
├── inference/
│   ├── classifier/
│   └── detector/
│
├── models/
│   ├── train_vit.py
│   ├── train_yolo.py
│   └── vit.py
│
├── utils.py
├── schema.py
├── main.py
└── requirements.txt
```

---

# Technologies

- Python 3.12
- FastAPI
- Uvicorn
- PyTorch
- TorchVision
- YOLOv8 (Ultralytics)
- Vision Transformer
- Pillow
- OpenCV
- NumPy
- HuggingFace Datasets

---

# Installation

Clone the repository

```bash
git clone https://github.com/<your-username>/StoneVisionAPI.git

cd StoneVisionAPI
```

Create a virtual environment

```bash
python -m venv .venv
```

Linux / macOS

```bash
source .venv/bin/activate
```

Windows

```powershell
.venv\Scripts\activate
```

Install dependencies

```bash
pip install -r requirements.txt
```

---

# Download the dataset

The project was trained using the HuggingFace dataset:

**Nech-C/mineralimage5K-98**

Generate the YOLO dataset

```bash
python prepare_yolo_dataset.py
```

---

# Train YOLO

```bash
python models/train_yolo.py
```

The detector learns a single class:

```
stone
```

Its only purpose is to locate the mineral inside the image.

---

# Train Vision Transformer

```bash
python models/train_vit.py
```

The ViT model classifies the cropped mineral into one of the supported mineral classes.

---

# Run the API

```bash
uvicorn main:app --reload --port 3000
```

Server

```
http://localhost:3000
```

---

# Swagger UI

```
http://localhost:3000/docs
```

---

# ReDoc

```
http://localhost:3000/redoc
```

---

# Predict a mineral

Using curl

```bash
curl -X POST \
-F "file=@image.jpg" \
http://localhost:3000/predict
```

Example response

```json
{
  "label": "amethyst",
  "confidence": 0.96,
  "detector_confidence": 0.98,
  "bbox": [
    152,
    87,
    682,
    541
  ],
  "top_k": [
    {
      "label": "amethyst",
      "confidence": 0.96
    },
    {
      "label": "fluorite",
      "confidence": 0.02
    }
  ]
}
```

---

# Model pipeline

The inference pipeline is divided into two independent stages.

## Detection

YOLO detects the mineral inside the image.

```
Image

↓

YOLO

↓

Bounding Box
```

## Classification

The detected mineral is cropped before being classified by a Vision Transformer.

```
Crop

↓

Vision Transformer

↓

Mineral name
```

This approach prevents background objects (hands, fingers, table, etc.) from influencing the classifier.

---

# Future improvements

- ONNX export
- TensorRT inference
- Batch prediction
- Multiple stone detection
- Docker deployment
- Kubernetes deployment
- Authentication (JWT)
- PostgreSQL prediction history
- Model versioning
- CI/CD

---

# FastAPI

This project is built using the **FastAPI** framework.

FastAPI provides:

- automatic OpenAPI generation
- automatic Swagger UI
- automatic ReDoc documentation
- request validation with Pydantic
- asynchronous endpoints
- excellent performance for machine learning APIs

The design of this API was inspired by the architecture and development philosophy presented in the official FastAPI project while implementing a custom computer vision pipeline for mineral recognition. :contentReference[oaicite:2]{index=2}

Official FastAPI website:

:contentReference[oaicite:3]{index=3}

Official FastAPI GitHub repository:

:contentReference[oaicite:4]{index=4}

---

# License

This project is released under the MIT License.

---

# Acknowledgements

- FastAPI
- Ultralytics
- PyTorch
- HuggingFace
- Vision Transformer
- YOLOv8

---

# Author

Mimine

Computer Vision • FastAPI • Deep Learning • Python