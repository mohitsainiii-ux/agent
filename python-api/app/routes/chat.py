from fastapi import APIRouter, HTTPException
from app.core.errors import ProcessingError
from app.config.settings import settings
from app.models.schemas import ChatRequest, ChatResponse, ErrorResponse
from app.services.gemini_service import generate_response
import logging

# Set up logging
logger = logging.getLogger(__name__)

# Create router instance
router = APIRouter(prefix="/chat", tags=["Chat"])

@router.post(
    "/",
    response_model=ChatResponse,
    responses={
        400: {"model": ErrorResponse},
        500: {"model": ErrorResponse}
    }
)
async def chat_endpoint(request: ChatRequest):
    """
    Send a message to the AI assistant and get a response.
    
    - **message**: Your question or prompt for the AI
    """
    try:
        logger.info(f"Received chat request: {request.message[:50]}...")
        
        # Get response from Gemini
        response_text = generate_response(request.message, request.history)
        
        # Determine if response is code (simple heuristic)
        response_type = "code" if any(marker in response_text.lower() for marker in 
                                     ["```", "def ", "class ", "function", "import "]) else "text"
        
        return ChatResponse(
            response=response_text,
            type=response_type,
            model=settings.GEMINI_MODEL,
        )
        
    except ValueError as error:
        logger.error("Configuration error: %s", error)
        raise HTTPException(
            status_code=503,
            detail="API key not configured. Please set GEMINI_API_KEY in .env file."
        )
    except Exception:
        logger.exception("Error processing chat")
        raise ProcessingError(
            "The AI service could not complete the request. Please try again.",
            status_code=502,
        )