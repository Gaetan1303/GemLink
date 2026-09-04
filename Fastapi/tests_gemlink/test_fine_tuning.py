import tempfile
import unittest
from pathlib import Path
from unittest.mock import AsyncMock, patch

import fine_tuning
from pydantic import ValidationError


def candidate(label: str, trust_score: int | None = None) -> dict:
    return {
        "media_url": f"https://media.example/{label}-{trust_score}.jpg",
        "label": label,
        "trust_score": trust_score,
    }


class FineTuningTest(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        fine_tuning.jobs.clear()
        fine_tuning.model_versions.clear()

    def make_request(self, **overrides):
        payload = {
            "job_id": "job-1",
            "model_version": "vit-v1.2.0",
            "min_trust_score": 70,
            "candidates": [
                candidate("quartz", 71),
                candidate("quartz", 90),
                candidate("amethyst", 80),
                candidate("amethyst", 100),
            ],
        }
        payload.update(overrides)
        return fine_tuning.FineTuneRequest.model_validate(payload)

    def test_requires_a_semantic_vit_version(self):
        with self.assertRaises(ValidationError):
            self.make_request(model_version="next-model")

    def test_rejects_partially_supplied_trust_scores(self):
        with self.assertRaises(ValidationError):
            self.make_request(
                candidates=[
                    candidate("quartz", 80),
                    candidate("quartz"),
                    candidate("amethyst", 90),
                    candidate("amethyst", 90),
                ]
            )

    def test_only_scores_strictly_above_threshold_are_eligible(self):
        request = self.make_request(
            candidates=[
                candidate("quartz", 70),
                candidate("quartz", 71),
                candidate("amethyst", 69),
                candidate("amethyst", 100),
            ]
        )

        eligible = fine_tuning._eligible_candidates(request)

        self.assertEqual([71, 100], [item.trust_score for item in eligible])

    async def test_dataset_contains_only_trusted_candidates(self):
        request = self.make_request(
            candidates=[
                candidate("quartz", 70),
                candidate("quartz", 71),
                candidate("quartz", 90),
                candidate("amethyst", 69),
                candidate("amethyst", 80),
                candidate("amethyst", 100),
            ]
        )

        async def fake_download(_session, _url, target):
            target.write_bytes(b"image")

        with tempfile.TemporaryDirectory() as directory:
            with patch.object(fine_tuning, "_download", new=AsyncMock(side_effect=fake_download)):
                count = await fine_tuning._prepare_dataset(request, object(), Path(directory))

        self.assertEqual(4, count)

    def test_job_state_and_model_registry_are_persisted(self):
        with tempfile.TemporaryDirectory() as directory:
            state_path = Path(directory) / "state.json"
            with patch.object(fine_tuning, "STATE_PATH", state_path):
                fine_tuning.jobs["job-1"] = {"status": "COMPLETED", "progress": 100}
                fine_tuning.register_active_version("vit-v1.2.0", "versions/vit-v1.2.0.pth")

                loaded_jobs, loaded_versions = fine_tuning._load_state()

        self.assertEqual("COMPLETED", loaded_jobs["job-1"]["status"])
        self.assertEqual("ACTIVE", loaded_versions["vit-v1.2.0"]["status"])
