from pydantic import BaseModel, Field
from typing import Optional, Literal

class ChatRequest(BaseModel):
    message: str = Field(..., min_length=1, description="User's message to the AI")
    
class ChatResponse(BaseModel):
    success: bool = True
    response: str = Field(..., description="AI's response")
    type: Literal["text", "code"] = Field(..., description="Type of response")
    
class ErrorResponse(BaseModel):
    error: str = Field(..., description="Error message")
    detail: Optional[str] = Field(None, description="Additional error details")