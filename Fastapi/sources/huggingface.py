from datasets import load_dataset
from pathlib import Path
from config import CLASSIFICATION_ROOT
from downloader.download import DownloadManager
from utils.logger import logger


class HuggingFaceScraper:

    def __init__(self):
        self.output = CLASSIFICATION_ROOT
        self.downloader = DownloadManager()

    def run(self, minerals):

        logger.info("[HuggingFace] Loading datasets...")

        dataset = load_dataset("Nech-C/mineralimage5K-98")

        for split in dataset.keys():

            for item in dataset[split]:

                label = item["label"] if "label" in item else "mineral"

                if label not in minerals:
                    continue

                image = item["image"]

                path = self.downloader.save_pil(
                    image,
                    class_name=label,
                    metadata={
                        "source": "huggingface",
                        "split": split
                    }
                )