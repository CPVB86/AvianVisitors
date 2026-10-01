from datetime import datetime, timedelta, timezone
from unittest.mock import patch
import secrets
import pytest
from fastapi.testclient import TestClient
from pydantic import ValidationError
from sqlalchemy.exc import IntegrityError, OperationalError, StatementError
from sqlalchemy.orm import Session
from app.core.config import ROOT, Settings
from app.main import create_app
from app.modules.birds.models import BirdDetection


TEST_TOKEN = secrets.token_urlsafe(32)
AUTH = {"Authorization": f"Bearer {TEST_TOKEN}"}


@pytest.fixture(autouse=True)
def api_environment(monkeypatch):
    monkeypatch.setenv("BACKYARD_API_TOKEN", TEST_TOKEN)


def settings(tmp_path):
    return Settings(_env_file=None, database_path=tmp_path / "db" / "test.sqlite3")


def detection(**overrides):
    values = dict(timestamp=datetime(2026, 9, 29, 12, tzinfo=timezone(timedelta(hours=2))),
                  scientific_name="Columba livia", common_name="Rotsduif",
                  confidence=0.944, source="test", raw_metadata={"window_seconds": 6})
    return BirdDetection(**(values | overrides))


def test_start_empty_and_restart_persistence(tmp_path):
    config = settings(tmp_path)
    app = create_app(config)
    with TestClient(app, headers=AUTH) as client:
        assert client.get("/api/health").json() == {
            "status": "ok", "service": "backyard", "database": "ok"}
        assert client.get("/api/birds/detections").json() == []
        with Session(app.state.engine) as session:
            row = detection()
            session.add(row)
            session.commit()
            identity = row.id
    with TestClient(create_app(config), headers=AUTH) as client:
        result = client.get("/api/birds/detections").json()
        assert len(result) == 1
        assert result[0]["id"] == identity
        assert result[0]["timestamp"] == "2026-09-29T10:00:00Z"
        assert result[0]["confidence"] == 0.944
        with Session(client.app.state.engine) as session:
            assert session.get(BirdDetection, identity).raw_metadata == {"window_seconds": 6}


@pytest.mark.parametrize("confidence", [-0.1, 1.1])
def test_database_confidence_constraint(tmp_path, confidence):
    with TestClient(create_app(settings(tmp_path)), headers=AUTH) as client:
        with Session(client.app.state.engine) as session:
            session.add(detection(confidence=confidence))
            with pytest.raises(IntegrityError):
                session.commit()


def test_naive_timestamp_rejected(tmp_path):
    with TestClient(create_app(settings(tmp_path)), headers=AUTH) as client:
        with Session(client.app.state.engine) as session:
            session.add(detection(timestamp=datetime(2026, 9, 29)))
            with pytest.raises(StatementError):
                session.commit()


def test_limit_and_health_failure(tmp_path):
    with TestClient(create_app(settings(tmp_path)), headers=AUTH) as client:
        for limit in (0, 101):
            assert client.get(f"/api/birds/detections?limit={limit}").status_code == 422
        with patch.object(client.app.state.engine, "connect",
                          side_effect=OperationalError("test", {}, Exception("private"))):
            response = client.get("/api/health")
            assert response.status_code == 503
            assert "private" not in response.text


def test_settings_precedence_and_validation(tmp_path, monkeypatch):
    env = tmp_path / ".env"
    env.write_text("BACKYARD_LOG_LEVEL=WARNING\n", encoding="utf-8")
    assert Settings(_env_file=env).log_level == "WARNING"
    monkeypatch.setenv("BACKYARD_LOG_LEVEL", "ERROR")
    assert Settings(_env_file=env).log_level == "ERROR"
    assert Settings(_env_file=None).resolved_database_path == ROOT / "data/backyard.sqlite3"
    monkeypatch.setenv("BACKYARD_LOG_LEVEL", "INVALID")
    with pytest.raises(ValidationError):
        Settings(_env_file=env)


@pytest.mark.parametrize("path", ["/api", "/api/health", "/api/health/", "/api/birds/detections", "/api/future-module",
                                  "/docs", "/docs/oauth2-redirect", "/redoc", "/openapi.json"])
def test_all_api_routes_require_bearer(tmp_path, path):
    with TestClient(create_app(settings(tmp_path))) as client:
        for header in (None, "Bearer wrong", "Basic " + TEST_TOKEN, "Bearer", "Bearer " + TEST_TOKEN + " extra"):
            response = client.get(path, headers={} if header is None else {"Authorization": header})
            assert response.status_code == 401
            assert response.json() == {"detail": "Unauthorized"}
            assert response.headers["www-authenticate"] == "Bearer"
            assert response.headers["cache-control"] == "no-store"
            assert TEST_TOKEN not in response.text
        for method in ("POST", "PUT", "DELETE", "OPTIONS"):
            assert client.request(method, path).status_code == 401


def test_auth_before_database_and_duplicate_headers(tmp_path):
    app = create_app(settings(tmp_path))
    with TestClient(app) as client:
        with patch.object(app.state.engine, "connect", side_effect=AssertionError("must not query")):
            assert client.get("/api/health").status_code == 401
        response = client.get("/api/health", headers=[("Authorization", "Bearer " + TEST_TOKEN)] * 2)
        assert response.status_code == 401
        response = client.get("/api/health", headers={"Authorization": "bearer " + TEST_TOKEN})
        assert response.status_code == 200
        assert response.headers["cache-control"] == "no-store"


def test_missing_or_invalid_token_fails_closed(tmp_path, monkeypatch):
    monkeypatch.delenv("BACKYARD_API_TOKEN")
    with pytest.raises(ValidationError):
        Settings(_env_file=None)
    for token in ("", "short", "x" * 42, "x" * 129, "x" * 43 + "\n", "x" * 43 + " "):
        with pytest.raises(ValidationError) as error:
            Settings(_env_file=None, api_token=token)
        if token:
            assert token not in str(error.value)
    config = Settings(_env_file=None, api_token=TEST_TOKEN)
    assert TEST_TOKEN not in repr(config)


def test_recent_birds_read_contract(tmp_path):
    app = create_app(settings(tmp_path))
    with TestClient(app, headers=AUTH) as client:
        with Session(app.state.engine) as session:
            session.add_all([
                detection(id="older", timestamp=datetime(2026, 1, 1, tzinfo=timezone.utc)),
                detection(id="new-a", timestamp=datetime(2026, 1, 2, tzinfo=timezone.utc)),
                detection(id="new-b", timestamp=datetime(2026, 1, 2, tzinfo=timezone.utc), common_name=None),
            ])
            session.commit()
        response = client.get("/api/birds/detections?limit=2")
        assert response.status_code == 200
        rows = response.json()
        assert [row["id"] for row in rows] == ["new-b", "new-a"]
        assert rows[0]["common_name"] is None
        assert rows[0]["scientific_name"] == "Columba livia"
        assert rows[0]["confidence"] == 0.944
        assert rows[0]["timestamp"] == "2026-01-02T00:00:00Z"
        assert "raw_metadata" not in rows[0]
