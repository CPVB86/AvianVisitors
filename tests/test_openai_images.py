"""Network-free tests. These never send a request to OpenAI or spend credits."""
import base64
from io import BytesIO
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.error import HTTPError

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / "avian/scripts"))
import openai_images
from demo.config import load_env
from demo.generate import prepare_prompt, save_cutout
import demo.server as server
import build_masks


class OpenAIImagesTests(unittest.TestCase):
    def test_generation_request(self):
        request = openai_images.build_request("test-secret", "bird")
        self.assertEqual(request.full_url, "https://api.openai.com/v1/images/generations")
        body = json.loads(request.data)
        self.assertEqual(body["n"], 1)
        self.assertEqual(body["background"], "transparent")
        self.assertNotIn(b"test-secret", request.data)

    def test_reference_request(self):
        reference = ROOT / "avian/assets/illustrations/passer-domesticus.png"
        request = openai_images.build_request("test-secret", "bird", [reference])
        self.assertTrue(request.full_url.endswith("/edits"))
        self.assertIn(b'name="image[]"', request.data)
        self.assertIn(reference.read_bytes(), request.data)
        self.assertNotIn(b"test-secret", request.data)

    def test_response_decode_and_no_retry(self):
        png = b"\x89PNG\r\n\x1a\nexample"
        def success(request, timeout):
            return BytesIO(json.dumps({"data": [{"b64_json": base64.b64encode(png).decode()}]}).encode())
        self.assertEqual(openai_images.generate_png("test-secret", "bird", opener=success), png)
        calls = []
        def rejected(request, timeout):
            calls.append(request)
            raise HTTPError(request.full_url, 401, "secret", {}, BytesIO(b"test-secret"))
        with self.assertRaises(RuntimeError) as caught:
            openai_images.generate_png("test-secret", "bird", opener=rejected)
        self.assertNotIn("test-secret", str(caught.exception))
        self.assertEqual(len(calls), 1)

    def test_bad_image_response(self):
        def invalid(request, timeout):
            return BytesIO(b'{"data": [{"b64_json": "aGVsbG8="}]}')
        with self.assertRaisesRegex(RuntimeError, "not a PNG"):
            openai_images.generate_png("test-secret", "bird", opener=invalid)

    def test_key_is_required_without_network(self):
        with self.assertRaisesRegex(ValueError, "OPENAI_API_KEY"):
            openai_images.build_request("", "bird")

    def test_env_precedence_and_quotes(self):
        with tempfile.TemporaryDirectory() as folder, patch.dict(os.environ, {"OPENAI_API_KEY": "shell-value"}, clear=True):
            path = Path(folder) / ".env"
            path.write_text('OPENAI_API_KEY="file-value"\nOPENAI_IMAGE_QUALITY=low\n', encoding="utf-8")
            load_env(path)
            self.assertEqual(os.environ["OPENAI_API_KEY"], "shell-value")
            self.assertEqual(os.environ["OPENAI_IMAGE_QUALITY"], "low")

    def test_prompt_reference_roles(self):
        prompt = prepare_prompt("Turdus merula", "Merel", 1, [("STYLE only", Path("style.png"))])
        self.assertIn("Turdus merula", prompt)
        self.assertIn("Image 1: STYLE only", prompt)
        self.assertNotIn("IMAGE 2", prompt)
        self.assertNotIn("{sci_name}", prompt)
        self.assertIn("fully transparent", prompt)

    def test_cutout_masks_and_local_overlay(self):
        from PIL import Image, ImageDraw
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            art, tables = root / "illustrations", root / "frontend"
            art.mkdir(); tables.mkdir()
            image = Image.new("RGBA", (100, 100))
            ImageDraw.Draw(image).ellipse((20, 10, 80, 90), fill=(40, 50, 60, 255))
            data = BytesIO(); image.save(data, format="PNG")
            target = art / "turdus-merula.png"
            save_cutout(data.getvalue(), target)
            dims, masks = build_masks.build_tables(art)
            for name, value in (("dims.json", dims), ("masks.json", masks)):
                (tables / name).write_text(json.dumps(value), encoding="utf-8")
            fixture = root / "species.json"
            fixture.write_text('[{"sci":"Turdus merula","com":"Merel","count":14}]')
            with patch.object(server, "LOCAL_ART", art), patch.object(server, "LOCAL_TABLES", tables):
                self.assertEqual(server.illustration("Turdus merula"), target)
                self.assertEqual(server.illustration("Turdus merula", 2), target)
                self.assertEqual(server.load_species(fixture)[0]["count"], 14)
                self.assertIn("passer-domesticus", server.art_table("dims.json"))
                self.assertIn("turdus-merula", server.art_table("masks.json"))

    def test_opaque_image_does_not_replace_art(self):
        from PIL import Image
        with tempfile.TemporaryDirectory() as folder:
            target = Path(folder) / "bird.png"
            target.write_bytes(b"existing")
            data = BytesIO(); Image.new("RGB", (20, 20), "white").save(data, format="PNG")
            with self.assertRaises(ValueError):
                save_cutout(data.getvalue(), target)
            self.assertEqual(target.read_bytes(), b"existing")


if __name__ == "__main__":
    unittest.main()
