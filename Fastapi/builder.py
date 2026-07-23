from sources.wikimedia import WikimediaScraper

from sources.openverse import OpenverseScraper

from sources.huggingface import HuggingFaceScraper


def main():

    WikimediaScraper().run()

    OpenverseScraper().run()

    HuggingFaceScraper().run()


if __name__ == "__main__":

    main()