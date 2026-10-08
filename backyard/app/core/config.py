from pathlib import Path
from typing import Literal
import re
from pydantic import SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

ROOT = Path(__file__).resolve().parents[2]


class BackyardSettings(BaseSettings):
    model_config = SettingsConfigDict(
        env_prefix="BACKYARD_", env_file=ROOT / ".env",
        env_file_encoding="utf-8", extra="forbid", hide_input_in_errors=True,
    )
    samsung_frame_host: str = ""
    samsung_frame_token: SecretStr | None = None
    samsung_frame_token_path: Path = Path("data/samsung_frame/token")
    samsung_frame_state_path: Path = Path("data/samsung_frame/artwork.json")
    samsung_frame_image_path: Path = Path("data/samsung_frame/samsung-frame.png")
    samsung_frame_timeout: float = 60
    database_path: Path = Path("data/backyard.sqlite3")
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"
    api_token: SecretStr | None = None

    @field_validator("samsung_frame_timeout")
    @classmethod
    def validate_timeout(cls, value: float) -> float:
        if not 1 <= value <= 300:
            raise ValueError("Samsung timeout must be 1–300 seconds")
        return value

    def resolve_path(self, path: Path) -> Path:
        path = path.expanduser()
        return path if path.is_absolute() else ROOT / path


class Settings(BackyardSettings):
    api_token: SecretStr

    @field_validator("api_token")
    @classmethod
    def validate_api_token(cls, value: SecretStr) -> SecretStr:
        if not re.fullmatch(r"[A-Za-z0-9_-]{43,128}", value.get_secret_value()):
            raise ValueError("Use a random URL-safe API token of 43–128 characters")
        return value

    @property
    def resolved_database_path(self) -> Path:
        path = self.database_path.expanduser()
        return path if path.is_absolute() else ROOT / path
