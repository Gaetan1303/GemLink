import asyncio
import aiohttp
from pathlib import Path
from config import CLASSIFICATION_ROOT
from downloader.download import DownloadManager
from utils.logger import logger


OPENVERSE_API = "https://api.openverse.engineering/v1/images"


class OpenverseScraper:

    def __init__(self):
        self.output = CLASSIFICATION_ROOT
        self.downloader = DownloadManager()

    async def fetch(self, session, query, page=1):
        params = {
            "q": query,
            "page": page,
            "page_size": 50
        }

        async with session.get(OPENVERSE_API, params=params) as r:
            return await r.json()

    async def process_query(self, session, mineral):
        page = 1
        tasks = []

        while page <= 3:  # limite simple pour éviter spam API

            data = await self.fetch(session, mineral, page)

            for item in data.get("results", []):

                url = item.get("url")
                if not url:
                    continue

                tasks.append(
                    self.downloader.download(
                        url=url,
                        class_name=mineral,
                        metadata={
                            "source": "openverse",
                            "license": item.get("license"),
                            "author": item.get("creator"),
                            "original": item.get("foreign_landing_url")
                        }
                    )
                )

            page += 1

        await asyncio.gather(*tasks)

    async def run_async(self, minerals):
        async with aiohttp.ClientSession() as session:
            for mineral in minerals:
                logger.info(f"[Openverse] {mineral}")
                await self.process_query(session, mineral)

    def run(self, minerals):
        asyncio.run(self.run_async(minerals))