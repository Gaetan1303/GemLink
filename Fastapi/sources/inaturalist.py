import aiohttp
import asyncio
from downloader.download import DownloadManager
from utils.logger import logger

INAT_API = "https://api.inaturalist.org/v1/observations"


MINERALS = [
    "agate",
    "jasper",
    "fluorite",
    "calcite",
    "pyrite",
    "hematite",
    "obsidian",
    "malachite",
    "garnet",
    "turquoise",
    "labradorite",
    "amazonite",
    "opal"
]


def is_valid_mineral(obs):
    taxon = obs.get("taxon", {})
    name = (taxon.get("name") or "").lower()

    bad = [
        "plant", "flower", "leaf",
        "fungi", "mushroom",
        "animal", "bird", "insect"
    ]

    return not any(b in name for b in bad)


class Scraper:

    def __init__(self):
        self.downloader = DownloadManager()

    async def fetch(self, session, query):
        params = {
            "q": query,
            "photos": "true",
            "per_page": 30,
            "quality_grade": "research",
            "verifiable": "true"
        }

        async with session.get(INAT_API, params=params) as r:
            return await r.json()

    async def run(self):

        async with aiohttp.ClientSession() as session:

            for mineral in MINERALS:
                logger.info(f"Searching {mineral}")

                data = await self.fetch(session, mineral)

                for obs in data.get("results", []):

                    if not is_valid_mineral(obs):
                        continue

                    photos = obs.get("photos", [])

                    for photo in photos:

                        url = photo.get("url")
                        if not url:
                            continue

                        url = url.replace("square", "large")

                        await self.downloader.download(
                            session,
                            url,
                            mineral,
                            metadata={"id": obs.get("id")}
                        )


if __name__ == "__main__":
    asyncio.run(Scraper().run())