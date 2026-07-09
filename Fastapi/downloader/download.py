import aiohttp
import hashlib
from pathlib import Path
from utils.logger import logger


class DownloadManager:
    def __init__(self, base_dir="dataset"):
        self.base_dir = Path(base_dir)
        self.base_dir.mkdir(exist_ok=True)

    def _hash(self, content: bytes):
        return hashlib.md5(content).hexdigest()[:12]

    async def download(self, session, url, class_name, metadata=None):

        class_dir = self.base_dir / class_name
        class_dir.mkdir(parents=True, exist_ok=True)

        try:
            async with session.get(url) as r:
                if r.status != 200:
                    return

                content = await r.read()

                file_hash = self._hash(content)

                file_path = class_dir / f"{file_hash}.jpg"

                # skip duplicates
                if file_path.exists():
                    return

                with open(file_path, "wb") as f:
                    f.write(content)

                logger.info(f"Saved {file_path}")

        except Exception as e:
            logger.error(e)