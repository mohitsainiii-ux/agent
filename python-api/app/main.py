from fastapi import FastAPI
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
from app.routes.chat import router as chat_router
from fastapi import HTTPException
from fastapi.exceptions import RequestValidationError
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

# Create FastAPI app
app = FastAPI(
    title="AI Agent API",
    description="Backend API for AI Agent with Gemini integration",
    version="1.0.0"
)

# Add CORS middleware (allows frontend to call API)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.add_exception_handler(ProcessingError, processing_error_handler)
app.add_exception_handler(HTTPException, http_error_handler)
app.add_exception_handler(RequestValidationError, validation_error_handler)
app.add_exception_handler(Exception, unhandled_error_handler)

app.include_router(health_router)
app.include_router(numpy_router)
app.include_router(chat_router)

@app.get("/")
async def root():
    return {"message": "AI Agent API is running", "docs": "/docs"}
