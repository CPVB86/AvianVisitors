from pathlib import Path
from typing import Literal
import re
from pydantic import SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

ROOT = Path(__file__).resolve().parents[2]


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_prefix="BACKYARD_", env_file=ROOT / ".env",
        env_file_encoding="utf-8", extra="forbid", hide_input_in_errors=True,
    )
    database_path: Path = Path("data/backyard.sqlite3")
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"
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
