import aiohttp
import asyncio
from downloader.download import DownloadManager
from utils.logger import logger


FLICKR_API = "https://www.flickr.com/services/rest/"


class FlickrScraper:

    def __init__(self, api_key=None):
        self.api_key = api_key
        self.downloader = DownloadManager()

    async def fetch(self, session, query):

        params = {
            "method": "flickr.photos.search",
            "api_key": self.api_key,
            "text": query,
            "format": "json",
            "nojsoncallback": 1,
            "per_page": 50,
            "license": "4,5,6,9,10"  # CC only
        }

        async with session.get(FLICKR_API, params=params) as r:
            return await r.json()

    def build_url(self, photo):

        return f"https://live.staticflickr.com/{photo['server']}/{photo['id']}_{photo['secret']}.jpg"

    async def run_async(self, minerals):

        async with aiohttp.ClientSession() as session:

            for mineral in minerals:

                data = await self.fetch(session, mineral)

                for photo in data.get("photos", {}).get("photo", []):

                    url = self.build_url(photo)

                    await self.downloader.download(
                        url=url,
                        class_name=mineral,
                        metadata={
                            "source": "flickr",
                            "id": photo.get("id")
                        }
                    )

    def run(self, minerals):
        asyncio.run(self.run_async(minerals))