"""Small, explicit .env loader for the local tools; never executes shell syntax."""
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ALLOWED = {"OPENAI_API_KEY", "OPENAI_IMAGE_MODEL", "OPENAI_IMAGE_QUALITY"}


def load_env(path=None):
    path = Path(path) if path else ROOT / ".env"
    if not path.is_file():
        return
    for number, raw in enumerate(path.read_text(encoding="utf-8-sig").splitlines(), 1):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        key, separator, value = line.partition("=")
        key, value = key.strip(), value.strip()
        if not separator or key not in ALLOWED:
            raise ValueError(f"Unsupported .env entry on line {number}")
        if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
            value = value[1:-1]
        os.environ.setdefault(key, value)
