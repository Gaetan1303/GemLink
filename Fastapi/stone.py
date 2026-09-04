"""
Billy — download_all_species_wikimedia.py (Version Corrigée)
Télécharge les images manquantes depuis Wikimedia Commons.
Correction de la boucle de téléchargement et de la récupération d'URLs.
"""

import time
from pathlib import Path

import requests

# ── Configuration des Dossiers ──
ENTRAINEMENTS_ROOT = Path.home() / "Desktop" / "entrainements_model_stones"
ENTRAINEMENTS_ROOT.mkdir(parents=True, exist_ok=True)
LOG_FILE = ENTRAINEMENTS_ROOT / f"log_telechargement_{time.strftime('%Y-%m-%d_%H-%M-%S')}.txt"

TRAIN_ROOT = ENTRAINEMENTS_ROOT / "train"
VALID_ROOT = ENTRAINEMENTS_ROOT / "valid"
TRAIN_ROOT.mkdir(parents=True, exist_ok=True)
VALID_ROOT.mkdir(parents=True, exist_ok=True)

print("─" * 60)
print(f"[DEBUG] Home Python        : {Path.home().resolve()}")
print(f"[DEBUG] ENTRAINEMENTS_ROOT : {ENTRAINEMENTS_ROOT.resolve()}")
print(f"[DEBUG] TRAIN_ROOT         : {TRAIN_ROOT.resolve()}")
print(f"[DEBUG] VALID_ROOT         : {VALID_ROOT.resolve()}")
print("─" * 60)

# ── Catalogue des Pierres, Minéraux et Roches ──
TOUTES_CLASSES = {
    "Quartz": "Quartz",
    "Amethyste": "Amethyst",
    "Citrine": "Citrine",
    "Quartz_Fume": "Smoky quartz",
    "Quartz_Rose": "Rose quartz",
    "Chalcedoine": "Chalcedony",
    "Agate": "Agate",
    "Jaspe": "Jasper",
    "Opale": "Opal",
    "Silex": "Chert",
    "Carneline": "Carnelian",
    "Chrysoprase": "Chrysoprase",
    "Onyx": "Onyx",
    "Heliotrope": "Bloodstone",
    "Feldspath": "Feldspar",
    "Orthose": "Orthoclase",
    "Plagioclase": "Plagioclase",
    "Labradorite": "Labradorite",
    "Amazonite": "Amazonite",
    "Pierre_de_lune": "Moonstone",
    "Biotite": "Biotite",
    "Muscovite": "Muscovite",
    "Lepidolite": "Lepidolite",
    "Hornblende": "Hornblende",
    "Augite": "Augite",
    "Olivine": "Olivine",
    "Peridot": "Peridot",
    "Grenat": "Garnet",
    "Almandin": "Almandine",
    "Pyrope": "Pyrope",
    "Andradite": "Andradite",
    "Grossulaire": "Grossular",
    "Epidote": "Epidote",
    "Zircon": "Zircon",
    "Staurotide": "Staurolite",
    "Cyanite": "Kyanite",
    "Andalousite": "Andalusite",
    "Silimanite": "Sillimanite",
    "Topaze": "Topaz",
    "Tourmaline": "Tourmaline",
    "Schorl": "Schorl",
    "Elbaite": "Elbaite",
    "Beryl": "Beryl",
    "Emeraude": "Emerald",
    "Aigue_marine": "Aquamarine",
    "Morganite": "Morganite",
    "Cordierite": "Cordierite",
    "Titanite": "Titanite",
    "Chrysocolle": "Chrysocolla",
    "Prehnite": "Prehnite",
    "Rhondonite": "Rhodonite",
    "Larimar": "Pectolite",
    "Sodalite": "Sodalite",
    "Lapis_lazuli": "Lapis lazuli",
    "Serpentine": "Serpentine",
    "Talc": "Talc",
    "Jadeite": "Jadeite",
    "Nephrite": "Nephrite",
    "Diopside": "Diopside",
    "Spodumene": "Spodumene",
    "Kunzite": "Kunzite",
    "Hiddenite": "Hiddenite",
    "Calcite": "Calcite",
    "Aragonite": "Aragonite",
    "Dolomite": "Dolomite",
    "Malachite": "Malachite",
    "Azurite": "Azurite",
    "Rhodochrosite": "Rhodochrosite",
    "Smithsonite": "Smithsonite",
    "Magnesite": "Magnesite",
    "Siderite": "Siderite",
    "Gypse": "Gypsum",
    "Selenite": "Selenite",
    "Anhydrite": "Anhydrite",
    "Barytine": "Baryte",
    "Celestine": "Celestine",
    "Fluorite": "Fluorite",
    "Halite": "Halite",
    "Sylvite": "Sylvite",
    "Apatite": "Apatite",
    "Turquoise": "Turquoise",
    "Monazite": "Monazite",
    "Pyromorphite": "Pyromorphite",
    "Vanalinite": "Vanadinite",
    "Wulfenite": "Wulfenite",
    "Hematite": "Hematite",
    "Magnetite": "Magnetite",
    "Chromite": "Chromite",
    "Ilmenite": "Ilmenite",
    "Rutile": "Rutile",
    "Cassiterite": "Cassiterite",
    "Pyrolusite": "Pyrolusite",
    "Bauxite": "Bauxite",
    "Limonite": "Limonite",
    "Goethite": "Goethite",
    "Corindon": "Corundum",
    "Rubis": "Ruby",
    "Saphir": "Sapphire",
    "Spinelle": "Spinel (mineral)",
    "Chrysoberyl": "Chrysoberyl",
    "Or_natif": "Native gold",
    "Argent_natif": "Native silver",
    "Cuivre_natif": "Native copper",
    "Soufre": "Sulfur",
    "Graphite": "Graphite",
    "Diamant": "Diamond",
    "Pyrite": "Pyrite",
    "Chalcopyrite": "Chalcopyrite",
    "Galene": "Galena",
    "Blende": "Sphalerite",
    "Cinabre": "Cinnabar",
    "Stibine": "Stibnite",
    "Realgar": "Realgar",
    "Orpiment": "Orpiment",
    "Bornite": "Bornite",
    "Molybdenite": "Molybdenite",
    "Arsenopyrite": "Arsenopyrite",
    "Granite": "Granite",
    "Basalte": "Basalt",
    "Obsidienne": "Obsidian",
    "Pumice": "Pumice",
    "Rhyolite": "Rhyolite",
    "Andesite": "Andesite",
    "Gabbro": "Gabbro",
    "Peridotite": "Peridotite",
    "Diorite": "Diorite",
    "Syenite": "Syenite",
    "Pegmatite": "Pegmatite",
    "Calcaire": "Limestone",
    "Gres": "Sandstone",
    "Schiste_Sedimentaire": "Shale",
    "Conglomerat": "Conglomerate (geology)",
    "Breche": "Breccia",
    "Coquina": "Coquina",
    "Craie": "Chalk",
    "Charbon": "Coal",
    "Marbre": "Marble",
    "Ardoise": "Slate",
    "Gneiss": "Gneiss",
    "Schiste_Metamorphique": "Schist",
    "Quartzite": "Quartzite",
    "Amphibolite": "Amphibolite",
    "Eclogite": "Eclogite",
}

OBJECTIF_TRAIN = 400
OBJECTIF_VALID = 200
PAUSE          = 0.2

COMMONS_API = "https://commons.wikimedia.org/w/api.php"
HEADERS = {
    "User-Agent": "BillyMinPierre(billy@gmail.com)"
}


def compter(dossier):
    if not dossier.exists():
        return 0
    return len([f for f in dossier.iterdir() if f.suffix.lower() in ('.jpg', '.jpeg', '.png')])


def noms_existants(dossier):
    if not dossier.exists():
        return set()
    return set(f.stem for f in dossier.iterdir() if f.suffix.lower() in ('.jpg', '.jpeg', '.png'))


def _get(params, retries=3):
    for attempt in range(retries):
        try:
            r = requests.get(COMMONS_API, params=params, headers=HEADERS, timeout=15)
            if r.status_code == 200:
                return r.json()
            if r.status_code == 429:
                time.sleep(2 * (attempt + 1))
                continue
            return None
        except requests.RequestException:
            time.sleep(1)
    return None


def lister_fichiers_categorie(taxon_name, limite=1200):
    """Récupère les titres de fichiers dans Category:{taxon_name}."""
    titres = []
    cmcontinue = None
    categorie = f"Category:{taxon_name}"
    while len(titres) < limite:
        params = {
            "action": "query",
            "format": "json",
            "list": "categorymembers",
            "cmtitle": categorie,
            "cmtype": "file",
            "cmlimit": "500",
        }
        if cmcontinue:
            params["cmcontinue"] = cmcontinue
        data = _get(params)
        if not data:
            break
        membres = data.get("query", {}).get("categorymembers", [])
        titres.extend(m["title"] for m in membres)
        cmcontinue = data.get("continue", {}).get("cmcontinue")
        if not cmcontinue:
            break
        time.sleep(PAUSE)
    return titres[:limite]


def rechercher_fichiers(taxon_name, limite=600):
    """Fallback recherche plein texte dans l'espace Fichier (6)."""
    titres = []
    offset = 0
    while len(titres) < limite:
        params = {
            "action": "query",
            "format": "json",
            "list": "search",
            "srsearch": f"{taxon_name} mineral" if "geology" not in taxon_name else taxon_name,
            "srnamespace": "6",
            "srlimit": "50",
            "sroffset": str(offset),
        }
        data = _get(params)
        if not data:
            break
        results = data.get("query", {}).get("search", [])
        if not results:
            break
        titres.extend(r["title"] for r in results)
        offset += 50
        if offset > 800:
            break
        time.sleep(PAUSE)
    return titres[:limite]


def recuperer_infos_images(titres, largeur=1024):
    """Récupère url + licence pour un lot de titres par paquets de 50."""
    infos = []
    for i in range(0, len(titres), 50):
        lot = titres[i:i + 50]
        params = {
            "action": "query",
            "format": "json",
            "titles": "|".join(lot),
            "prop": "imageinfo",
            "iiprop": "url|extmetadata|size|mime",
            "iiurlwidth": str(largeur),
        }
        data = _get(params)
        if not data:
            continue
        pages = data.get("query", {}).get("pages", {})
        for page in pages.values():
            imageinfo = page.get("imageinfo")
            if not imageinfo:
                continue
            info = imageinfo[0]
            mime = info.get("mime", "")
            if not mime.startswith("image/"):
                continue
                
            # CORRECTIF SÉCURITÉ : Récupération de l'URL brute si la miniature échoue
            url = info.get("thumburl") or info.get("url")
            if not url:
                continue
                
            licence = (
                info.get("extmetadata", {})
                .get("LicenseShortName", {})
                .get("value", "inconnue")
            )
            pageid = page.get("pageid", page.get("title", "x"))
            infos.append({"pageid": pageid, "url": url, "licence": licence})
        time.sleep(PAUSE)
    return infos

def telecharger_images(infos, existants, dossier_train, dossier_valid, manquant_train, manquant_valid, log_licences=None):
    """Télécharge les images en comblant précisément le nombre d'images manquantes."""
    dl_train = 0
    dl_valid = 0
    
    for info in infos:
        # Si on a complété les deux dossiers, on s'arrête de parcourir ce lot
        if dl_train >= manquant_train and dl_valid >= manquant_valid:
            break
            
        nom = f"commons_{info['pageid']}"
        if nom in existants:
            continue
            
        try:
            # Récupération sécurisée de l'image
            img_r = requests.get(info["url"], headers=HEADERS, timeout=15)
            
            if img_r.status_code == 200 and len(img_r.content) > 5000:
                
                if dl_train < manquant_train:
                    dest = dossier_train / f"{nom}.jpg"
                    tag = "T"
                    dl_train += 1
                elif dl_valid < manquant_valid:
                    dest = dossier_valid / f"{nom}.jpg"
                    tag = "V"
                    dl_valid += 1
                else:
                    continue
                    
                # Écriture du fichier sur le disque
                dest.write_bytes(img_r.content)
                existants.add(nom)
                
                if log_licences is not None:
                    log_licences.append(f"{nom}.jpg :: {info['licence']}")
                    
                # Sécurisation de l'affichage (on force l'encodage ascii/ignore pour éviter les crashs de string)
                licence_str = str(info['licence'])[:15].encode('ascii', 'ignore').decode('ascii')
                print(f"    [{tag}] +{dl_train}/{manquant_train}T  +{dl_valid}/{manquant_valid}V  ({licence_str})", end='\r', flush=True)
                
                time.sleep(PAUSE)
                
        except Exception as e:
            # Super-sécurité : quoi qu'il arrive (Timeout, SSL, Disque plein, String mal encodée),
            # on ne coupe pas le script, on passe juste à l'image suivante.
            pass
            
    return dl_train, dl_valid

def traiter_classe(taxon_name, dossier_train, dossier_valid, manquant_train, manquant_valid, log_licences):
    dossier_train.mkdir(parents=True, exist_ok=True)
    dossier_valid.mkdir(parents=True, exist_ok=True)
    existants = noms_existants(dossier_train) | noms_existants(dossier_valid)

    # Étape 1 — Scan de la catégorie dédiée (limite augmentée à 1200)
    titres = lister_fichiers_categorie(taxon_name, limite=1200)
    print(f"    Catégorie 'Category:{taxon_name}' : {len(titres)} fichiers trouvés")

    # Étape 2 — Scan via recherche textuelle si le quota requis est faible
    if len(titres) < (manquant_train + manquant_valid + 50):
        print(f"    → Fin de catégorie ou pauvre. Fallback recherche plein texte...")
        titres_recherche = rechercher_fichiers(taxon_name, limite=600)
        for t in titres_recherche:
            if t not in titres:
                titres.append(t)

    if not titres:
        print(f"    ⚠️ Aucun fichier trouvé pour : {taxon_name} — skip")
        return 0, 0

    infos = recuperer_infos_images(titres)
    
    # Appel du téléchargement avec passage des volumes manquants réels
    dt, dv = telecharger_images(
        infos, existants, dossier_train, dossier_valid, manquant_train, manquant_valid,
        log_licences=log_licences
    )

    return dt, dv


def log(msg):
    print(msg)
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(msg + "\n")
    except OSError:
        pass


# ── Main Loop ──
log("\n" + "═" * 60)
log("  Billy — Téléchargement des minéraux et des roches (Wikimedia Commons)")
log(f"  {len(TOUTES_CLASSES)} classes actives · objectif {OBJECTIF_TRAIN} train / {OBJECTIF_VALID} valid")
log(f"  Dossier d'entraînement : {ENTRAINEMENTS_ROOT}")
log("═" * 60)

total_train = 0
total_valid = 0
skipped     = 0
toutes_licences = []

for dossier_nom, taxon in TOUTES_CLASSES.items():
    d_train = TRAIN_ROOT / dossier_nom
    d_valid = VALID_ROOT / dossier_nom

    n_train = compter(d_train)
    n_valid = compter(d_valid)

    manquant_train = max(0, OBJECTIF_TRAIN - n_train)
    manquant_valid = max(0, OBJECTIF_VALID - n_valid)

    log(f"\n  [{dossier_nom}]")
    log(f"    Départ local -> Train : {n_train} | Valid : {n_valid} (Manque : {manquant_train}T / {manquant_valid}V)")

    if manquant_train == 0 and manquant_valid == 0:
        log("    ✅ Déjà Complet, skip")
        skipped += 1
        continue

    licences_classe = []
    dt, dv = traiter_classe(taxon, d_train, d_valid, manquant_train, manquant_valid, licences_classe)
    toutes_licences.extend(f"[{dossier_nom}] {l}" for l in licences_classe)
    
    total_train += dt
    total_valid += dv
    log(f"\n    ✅ Ajouté avec succès : +{dt} train  |  +{dv} valid")
    time.sleep(0.5)

if toutes_licences:
    licences_file = ENTRAINEMENTS_ROOT / f"licences_{time.strftime('%Y-%m-%d_%H-%M-%S')}.txt"
    licences_file.write_text("\n".join(toutes_licences), encoding="utf-8")
    log(f"\n  📄 Détail des licences sauvegardé dans : {licences_file}")

log(f"\n{'═'*60}")
log(f"  ✅ Session Terminée — Train global +{total_train}  |  Valid global +{total_valid}  |  Classes ignorées : {skipped}")
log(f"  Log complet de session : {LOG_FILE}")
log("═" * 60 + "\n")