import aiohttp
import asyncio
from config import CLASSIFICATION_ROOT
from downloader.download import DownloadManager
from utils.logger import logger


SMITHSONIAN_API = "https://api.si.edu/openaccess/api/v1.0/search"


class SmithsonianScraper:

    def __init__(self):
        self.downloader = DownloadManager()

    async def fetch(self, session, query):

        params = {
            "q": query,
            "rows": 50,
            "api_key": "public"
        }

        async with session.get(SMITHSONIAN_API, params=params) as r:
            return await r.json()

    async def run_async(self, minerals):

        async with aiohttp.ClientSession() as session:

            for mineral in minerals:

                data = await self.fetch(session, mineral)

                for item in data.get("response", {}).get("rows", []):

                    if "online_media" not in item:
                        continue

                    for media in item["online_media"]["media"]:

                        url = media.get("content")

                        if not url:
                            continue

                        await self.downloader.download(
                            url=url,
                            class_name=mineral,
                            metadata={
                                "source": "smithsonian",
                                "title": item.get("title")
                            }
                        )

    def run(self, minerals):
        asyncio.run(self.run_async(minerals))