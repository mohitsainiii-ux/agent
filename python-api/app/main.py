from fastapi import FastAPI, HTTPException
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware

from app.core.errors import (
    ProcessingError,
    http_error_handler,
    processing_error_handler,
    unhandled_error_handler,
    validation_error_handler,
)
from app.routers.health import router as health_router
from app.routers.numpy_routes import router as numpy_router

app = FastAPI(
    title="ChatBot Python API",
    version="1.0.0",
    description="NumPy processing API for the existing CodeIgniter application.",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

app.add_exception_handler(ProcessingError, processing_error_handler)
app.add_exception_handler(RequestValidationError, validation_error_handler)
app.add_exception_handler(HTTPException, http_error_handler)
app.add_exception_handler(Exception, unhandled_error_handler)

app.include_router(health_router)
app.include_router(numpy_router)


@app.get("/")
def root() -> dict:
    return {
        "success": True,
        "service": "python-api",
        "message": "Python NumPy API is running.",
        "health": "/health",
        "numpy": "/numpy/process",
    }
