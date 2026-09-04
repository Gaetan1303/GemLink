import hashlib
import json
import tempfile
import unittest
from pathlib import Path

from training.dataset_manifest import load_manifest, verify_file


class DatasetManifestTest(unittest.TestCase):
    def make_record(self, image: bytes, **overrides):
        record = {
            "source": "Wikimedia Commons",
            "url": "https://commons.wikimedia.org/wiki/File:Quartz.jpg",
            "author": "Example",
            "license": "CC-BY-4.0",
            "imported_at": "2026-01-01T00:00:00Z",
            "sha256": hashlib.sha256(image).hexdigest(),
            "class": "quartz",
            "local_path": "images/train/quartz.jpg",
        }
        record.update(overrides)
        return record

    def test_manifest_requires_approved_license_and_matching_hash(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            image = root / "images/train/quartz.jpg"
            image.parent.mkdir(parents=True)
            image.write_bytes(b"image")
            manifest = root / "dataset_manifest.jsonl"
            manifest.write_text(json.dumps(self.make_record(b"image")) + "\n", encoding="utf-8")

            records = load_manifest(manifest)
            record = verify_file(image, root, records)

        self.assertEqual("CC-BY-4.0", record.license)

    def test_non_commercial_license_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            manifest = Path(directory) / "dataset_manifest.jsonl"
            manifest.write_text(
                json.dumps(self.make_record(b"image", license="CC-BY-NC-4.0")) + "\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(ValueError, "dataset license rejected: NC"):
                load_manifest(manifest)
