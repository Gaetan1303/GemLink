from ultralytics import YOLO


def main():
    model = YOLO("yolov8n.pt")

    model.train(
        data="data/detection/data.yaml",
        epochs=50,
        imgsz=640,
        batch=32,
        workers=0,   # IMPORTANT sur Windows
        device=0,
        project="runs",
        name="stone_detector"
    )


if __name__ == "__main__":
    main()