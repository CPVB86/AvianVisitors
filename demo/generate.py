"""Generate local transparent bird illustrations with OpenAI, then build masks.

Example: python demo/generate.py --sci "Turdus merula" --com "Merel" --pose both
Use --dry-run to inspect the prompt without an API call. Default: one pose.
Outputs stay in ignored .avian/; production assets and Gemini remain intact.
"""
import argparse
from io import BytesIO
import json
import logging
import os
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / "avian/scripts"))
from demo.config import load_env
import build_masks
import openai_images
import pregen


def prepare_prompt(sci, com, pose, references):
    prompt = pregen.load_prompt(ROOT / "avian/scripts/prompt.template.md")
    prompt = prompt.replace("{sci_name}", sci).replace("{com_name}", com)
    prompt = prompt.replace("{pose}", pregen.POSES[pose]).replace("{anti_ref_line}", "")
    # The legacy template mixes IMAGE 2/3 for style. Bind references by role
    # instead, so optional anatomy/anti-reference files cannot shift meaning.
    prompt = prompt.replace("IMAGE 2", "the STYLE reference").replace("IMAGE 3", "the STYLE reference")
    prompt = prompt.replace("IMAGE 1", "the ANATOMY reference (if supplied; otherwise use the named species)")
    notes = pregen.load_species_notes(ROOT / "avian/scripts/species-notes.json")
    if sci in notes:
        prompt += "\nSpecies-specific note: " + notes[sci]
    prompt += "\n\nReference images, in upload order:\n" + "\n".join(
        f"Image {i + 1}: {label}" for i, (label, _) in enumerate(references))
    prompt += ("\nOUTPUT OVERRIDE: Ignore the cream-ground/background instructions above. "
               "Return one bird isolated on a fully transparent background with a clean alpha channel. "
               "No text, branches, shadow or border. Keep the entire bird including wings, tail and feet "
               "inside the image with generous transparent margins. The style reference supplies painting "
               "technique only; do not copy its species or anatomy.")
    return prompt


def save_cutout(data, destination):
    from PIL import Image
    image = Image.open(BytesIO(data)).convert("RGBA")
    alpha = image.getchannel("A")
    if alpha.getextrema()[0] > 0 or not alpha.getbbox():
        raise ValueError("Image lacks a transparent background or visible bird; raw image kept for review")
    box = alpha.getbbox()
    padding = max(8, round(max(box[2] - box[0], box[3] - box[1]) * .04))
    bird = image.crop(box)
    canvas = Image.new("RGBA", (bird.width + padding * 2, bird.height + padding * 2))
    canvas.paste(bird, (padding, padding))
    temporary = destination.with_suffix(".tmp.png")
    canvas.save(temporary)
    temporary.replace(destination)


def main():
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sci", required=True)
    parser.add_argument("--com", required=True)
    parser.add_argument("--pose", choices=["perched", "flight", "both"], default="perched")
    parser.add_argument("--reference", type=Path, help="Target-species anatomy photo")
    parser.add_argument("--anti-reference", type=Path, help="Lookalike photo: do not copy its markings")
    parser.add_argument("--style-reference", type=Path, help="Override the bundled style illustration")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--force", action="store_true", help="Explicitly regenerate existing local images")
    args = parser.parse_args()
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    try:
        load_env()
    except (OSError, ValueError):
        parser.exit(1, "Configuratie ongeldig; controleer .env (waarden worden niet gelogd).\n")
    if not re.fullmatch(r"[A-Za-z]{2,40}(?: [a-z]{2,40}){1,3}", args.sci):
        parser.error("Invalid scientific name")
    model = os.environ.get("OPENAI_IMAGE_MODEL") or openai_images.DEFAULT_MODEL
    quality = os.environ.get("OPENAI_IMAGE_QUALITY") or "medium"
    poses = [1, 2] if args.pose == "both" else [1 if args.pose == "perched" else 2]
    output = ROOT / ".avian/illustrations"
    frontend = ROOT / ".avian/frontend"
    if not args.dry_run:
        try:
            import PIL
        except ImportError:
            parser.error("Install optional dependencies: python -m pip install -r demo/requirements-images.txt")
        output.mkdir(parents=True, exist_ok=True)
        frontend.mkdir(parents=True, exist_ok=True)
    lock = ROOT / ".avian/image-generation.lock"
    handle = None
    try:
        if not args.dry_run:
            handle = lock.open("x")
        for pose in poses:
            slug = pregen.slugify(args.sci) + ("-2" if pose == 2 else "")
            target = output / (slug + ".png")
            if target.exists() and not args.force:
                print(f"Skipped existing {slug}.png; use --force to regenerate")
                continue
            refs = []
            if args.reference:
                refs.append(("ANATOMY of the target species", args.reference))
            if args.anti_reference:
                refs.append(("NEGATIVE lookalike; do NOT copy markings", args.anti_reference))
            refs.append(("STYLE only", args.style_reference or ROOT / f"avian/assets/illustrations/turdus-migratorius{'-2' if pose == 2 else ''}.png"))
            for _, path in refs:
                if not path.is_file():
                    raise ValueError("Reference image does not exist: " + str(path))
            prompt = prepare_prompt(args.sci, args.com, pose, refs)
            print(f"{slug}: model={model}, quality={quality}, one 1024x1024 PNG")
            if args.dry_run:
                print(prompt)
                continue
            logging.info("Beeldgeneratie gestart: %s", slug)
            data = openai_images.generate_png(os.environ.get("OPENAI_API_KEY", ""), prompt,
                                             [path for _, path in refs], model, quality)
            raw = output / "raw"
            raw.mkdir(exist_ok=True)
            (raw / target.name).write_bytes(data)
            save_cutout(data, target)
            logging.info("Beeldgeneratie geslaagd: %s", target)
        if not args.dry_run:
            dims, masks = build_masks.build_tables(output)
            for name, table in (("dims.json", dims), ("masks.json", masks)):
                temp = frontend / (name + ".tmp")
                temp.write_text(build_masks.dump_perkey(table), encoding="utf-8")
                temp.replace(frontend / name)
            print("Local masks updated. Reload the local browser to use the illustrations.")
    except FileExistsError:
        parser.exit(1, "Another generation holds .avian/image-generation.lock. If a previous process crashed, remove that lock only after it has stopped.\n")
    except (ValueError, RuntimeError, OSError) as error:
        logging.error("Beeldgeneratie mislukt: %s", error)
        parser.exit(1)
    finally:
        if handle:
            handle.close()
            lock.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
