from contextlib import asynccontextmanager
import logging
import secrets
from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from app.core.config import Settings
from app.core.database import create_database, initialize_database
from app.core.logging import configure_logging
from app.modules.birds.router import router as birds_router

logger = logging.getLogger("backyard.api")


def create_app(settings: Settings | None = None) -> FastAPI:
    @asynccontextmanager
    async def lifespan(app):
        config = settings if settings is not None else Settings()
        app.state.api_token = config.api_token
        configure_logging(config.log_level)
        engine = create_database(config)
        try:
            initialize_database(engine)
            app.state.engine = engine
            logger.info("Backyard started")
            yield
        finally:
            engine.dispose()
            logger.info("Backyard stopped")

    app = FastAPI(title="Backyard", version="0.1.0", lifespan=lifespan)

    @app.middleware("http")
    async def authenticate_api(request: Request, call_next):
        # Central gate also covers future modules, unknown routes and HTTP methods.
        protected = request.url.path == "/api" or request.url.path.startswith("/api/") or request.url.path in {
            "/docs", "/docs/oauth2-redirect", "/redoc", "/openapi.json",
        }
        if protected:
            headers = request.headers.getlist("authorization")
            scheme, _, token = (headers[0] if len(headers) == 1 else "").partition(" ")
            expected = request.app.state.api_token.get_secret_value()
            if scheme.lower() != "bearer" or not secrets.compare_digest(
                token.encode("utf-8"), expected.encode("utf-8")
            ):
                return JSONResponse(
                    status_code=401, content={"detail": "Unauthorized"},
                    headers={"WWW-Authenticate": "Bearer", "Cache-Control": "no-store"},
                )
        response = await call_next(request)
        if protected:
            response.headers["Cache-Control"] = "no-store"
        return response

    app.include_router(birds_router)

    @app.get("/api/health", tags=["core"])
    def health(request: Request):
        try:
            with request.app.state.engine.connect() as connection:
                connection.execute(text("SELECT 1"))
        except SQLAlchemyError:
            logger.error("Database health check failed")
            return JSONResponse(status_code=503, content={
                "status": "error", "database": "unavailable",
            })
        return {"status": "ok", "service": "backyard", "database": "ok"}

    return app


app = create_app()
