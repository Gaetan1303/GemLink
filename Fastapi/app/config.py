from functools import lru_cache
from pathlib import Path

from pydantic import SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Single source of truth for the FastAPI runtime configuration."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
        case_sensitive=False,
    )

    app_env: str = "development"
    app_host: str = "0.0.0.0"
    port: int = 3000

    internal_api_key: SecretStr | None = None
    ai_enabled: bool = True
    vision_enabled: bool = True
    llm_enabled: bool = True

    ollama_url: str = "http://ollama:11434"
    ollama_text_model: str = "qwen3:0.6b"
    ollama_vision_model: str = "moondream"

    detector_backend: str = "torchvision"
    detector_model_path: Path = Path("checkpoints/detector_stones.pth")
    detector_model_version: str = "stone-detector-v1"
    detection_confidence_threshold: float = 0.5

    vit_model_path: Path = Path("checkpoints/vit_stones.pth")
    vit_model_version: str = "vit-stones-v1"
    vit_versions_root: Path = Path("checkpoints/versions")

    clip_model_arch: str = "ViT-B-32"
    clip_model_path: Path = Path("checkpoints/clip_vit_b_32_openai.pt")
    clip_model_pretrained: str = "openai"
    clip_model_version: str = "clip-vit-b-32-openai"

    media_internal_base_url: str = ""
    fine_tune_state_path: Path = Path("checkpoints/fine_tuning_state.json")
    fine_tune_max_log_lines: int = 500
    fine_tune_epochs: int = 15

    max_image_bytes: int = 10 * 1024 * 1024
    max_video_bytes: int = 100 * 1024 * 1024

    @field_validator("app_env")
    @classmethod
    def validate_environment(cls, value: str) -> str:
        normalized = value.strip().lower()
        if normalized not in {"development", "test", "production"}:
            raise ValueError("APP_ENV must be development, test or production")
        return normalized

    @field_validator("detector_backend")
    @classmethod
    def validate_detector_backend(cls, value: str) -> str:
        normalized = value.strip().lower()
        if normalized != "torchvision":
            raise ValueError("Only DETECTOR_BACKEND=torchvision is supported in production")
        return normalized

    @property
    def internal_api_key_configured(self) -> bool:
        if self.internal_api_key is None:
            return False
        value = self.internal_api_key.get_secret_value()
        return len(value) >= 32

    @property
    def internal_api_key_value(self) -> str:
        return self.internal_api_key.get_secret_value() if self.internal_api_key else ""


@lru_cache
def get_settings() -> Settings:
    return Settings()
