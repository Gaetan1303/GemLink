from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from urllib.parse import urlparse


ALLOWED_LICENSES = {
    "CC0-1.0",
    "PDM-1.0",
    "CC-BY-3.0",
    "CC-BY-4.0",
}
REJECTED_LICENSE_MARKERS = {
    "NC",
    "ND",
    "UNKNOWN",
    "ALL-RIGHTS-RESERVED",
    "RESEARCH-ONLY",
}


@dataclass(frozen=True)
class DatasetRecord:
    source: str
    url: str
    author: str
    license: str
    imported_at: str
    sha256: str
    class_name: str
    local_path: str

    @classmethod
    def from_dict(cls, payload: dict) -> "DatasetRecord":
        required = {
            "source",
            "url",
            "author",
            "license",
            "imported_at",
            "sha256",
            "class",
            "local_path",
        }
        missing = sorted(required - payload.keys())
        if missing:
            raise ValueError(f"manifest fields missing: {', '.join(missing)}")

        record = cls(
            source=str(payload["source"]).strip(),
            url=str(payload["url"]).strip(),
            author=str(payload["author"]).strip(),
            license=str(payload["license"]).strip().upper(),
            imported_at=str(payload["imported_at"]).strip(),
            sha256=str(payload["sha256"]).strip().lower(),
            class_name=str(payload["class"]).strip(),
            local_path=str(payload["local_path"]).strip(),
        )
        record.validate()
        return record

    def validate(self) -> None:
        if not all((self.source, self.author, self.class_name, self.local_path)):
            raise ValueError("manifest provenance fields must not be empty")
        if self.license not in ALLOWED_LICENSES:
            marker = next((item for item in REJECTED_LICENSE_MARKERS if item in self.license), None)
            reason = marker or self.license
            raise ValueError(f"dataset license rejected: {reason}")
        parsed = urlparse(self.url)
        if parsed.scheme != "https" or not parsed.netloc:
            raise ValueError("manifest URL must be an absolute HTTPS URL")
        try:
            datetime.fromisoformat(self.imported_at.replace("Z", "+00:00"))
        except ValueError as error:
            raise ValueError("imported_at must be an ISO-8601 timestamp") from error
        if len(self.sha256) != 64 or any(char not in "0123456789abcdef" for char in self.sha256):
            raise ValueError("sha256 must contain 64 hexadecimal characters")


def load_manifest(path: Path) -> dict[str, DatasetRecord]:
    records: dict[str, DatasetRecord] = {}
    with path.open(encoding="utf-8") as stream:
        for line_number, line in enumerate(stream, start=1):
            if not line.strip():
                continue
            try:
                record = DatasetRecord.from_dict(json.loads(line))
            except (json.JSONDecodeError, TypeError, ValueError) as error:
                raise ValueError(f"invalid manifest line {line_number}: {error}") from error
            if record.local_path in records:
                raise ValueError(f"duplicate manifest local_path: {record.local_path}")
            records[record.local_path] = record
    if not records:
        raise ValueError("dataset manifest is empty")
    return records


def verify_file(image_path: Path, dataset_root: Path, records: dict[str, DatasetRecord]) -> DatasetRecord:
    local_path = image_path.resolve().relative_to(dataset_root.resolve()).as_posix()
    record = records.get(local_path)
    if record is None:
        raise ValueError(f"missing provenance for {local_path}")
    digest = hashlib.sha256(image_path.read_bytes()).hexdigest()
    if digest != record.sha256:
        raise ValueError(f"hash mismatch for {local_path}")
    return record
