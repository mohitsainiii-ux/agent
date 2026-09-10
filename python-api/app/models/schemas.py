from pydantic import BaseModel, Field
from typing import Optional, Literal

class ChatHistoryMessage(BaseModel):
    role: Literal["user", "assistant", "system"]
    content: str = Field(..., min_length=1, max_length=100000)

class ChatRequest(BaseModel):
    message: str = Field(..., min_length=1, max_length=100000, description="User's message to the AI")
    history: list[ChatHistoryMessage] = Field(default_factory=list, max_length=100, description="Earlier messages in this conversation")
    
class ChatResponse(BaseModel):
    success: bool = True
    response: str = Field(..., description="AI's response")
    type: Literal["text", "code"] = Field(..., description="Type of response")
    model: Optional[str] = Field(None, description="Model used for the response")
    
class ErrorResponse(BaseModel):
    error: str = Field(..., description="Error message")
    detail: Optional[str] = Field(None, description="Additional error details")