from __future__ import annotations

import asyncio
import json
import logging
from pathlib import Path

import aiofiles
import aiohttp
from tqdm.asyncio import tqdm

# Configuration de l'API
WIKIMEDIA_API = "https://commons.wikimedia.org/w/api.php"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s"
)
logger = logging.getLogger("Wikimedia")

class WikimediaScraper:
    def __init__(self, output_dir: Path, concurrency: int = 2):
        self.output_dir = output_dir
        self.concurrency = concurrency
        self.timeout = aiohttp.ClientTimeout(total=60)
        self.headers = {
            "User-Agent": "StoneVisionDatasetBuilder/1.0 (contact: billy@gmail.com)"
        }
        self.output_dir.mkdir(parents=True, exist_ok=True)

    async def create_session(self) -> aiohttp.ClientSession:
        connector = aiohttp.TCPConnector(limit=self.concurrency)
        return aiohttp.ClientSession(connector=connector, timeout=self.timeout)

    async def request(self, session: aiohttp.ClientSession, params: dict) -> dict:
        async with session.get(WIKIMEDIA_API, params=params, headers=self.headers) as response:
            response.raise_for_status()
            return await response.json()

    async def search_images(self, session: aiohttp.ClientSession, keyword: str) -> list[dict]:
        logger.info("Recherche des images pour : %s", keyword)
        results = []
        continuation = {}
        while True:
            params = {
                "action": "query", "generator": "search",
                "gsrsearch": f"{keyword} filetype:bitmap|drawing",
                "gsrnamespace": 6, "gsrlimit": "500", "prop": "imageinfo",
                "iiprop": "url|mime", "format": "json"
            }
            params.update(continuation)
            data = await self.request(session, params)
            pages = data.get("query", {}).get("pages", {})
            results.extend(pages.values())
            if "continue" not in data: break
            continuation = data["continue"]
        return results

    async def download_image(self, session: aiohttp.ClientSession, image: dict, mineral_name: str) -> bool:
        imageinfo = image.get("imageinfo", [{}])[0]
        url = imageinfo.get("url")
        mime = imageinfo.get("mime", "")
        if not url or not mime.startswith("image/"): return False

        title = image.get("title", "unknown")
        filename = title.replace("File:", "").replace("/", "_")
        target_dir = self.output_dir / mineral_name
        target_dir.mkdir(parents=True, exist_ok=True)
        image_path = target_dir / filename

        if image_path.exists(): return True

        await asyncio.sleep(0.8)
        try:
            async with session.get(url, headers=self.headers) as response:
                if response.status == 429:
                    await asyncio.sleep(10)
                    return False
                response.raise_for_status()
                async with aiofiles.open(image_path, "wb") as f:
                    await f.write(await response.read())
                return True
        except Exception as e:
            logger.error(f"Erreur sur {filename}: {e}")
            return False

    async def process_mineral(self, session: aiohttp.ClientSession, mineral_name: str):
        images = await self.search_images(session, mineral_name)
        semaphore = asyncio.Semaphore(self.concurrency)
        async def worker(image):
            async with semaphore:
                await self.download_image(session, image, mineral_name)
        tasks = [asyncio.create_task(worker(img)) for img in images]
        for task in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc=mineral_name):
            await task

    async def run_async(self, minerals: list[str]):
        async with await self.create_session() as session:
            for mineral in minerals:
                await self.process_mineral(session, mineral)

if __name__ == "__main__":
    minerals_to_download = [
        "Labradorite", "Quartz", "Amethyst", "Citrine", "Smoky_Quartz", "Feldspar", "Mica", 
        "Garnet", "Olivine", "Calcite", "Aragonite", "Malachite", "Azurite", 
        "Dolomite", "Pyrite", "Galena", "Chalcopyrite", "Sphalerite", 
        "Hematite", "Magnetite", "Corundum", "Goethite", "Fluorite", 
        "Halite", "Barite", "Gypsum"
    ]
    
    scraper = WikimediaScraper(Path("dataset/classification"))
    asyncio.run(scraper.run_async(minerals_to_download))