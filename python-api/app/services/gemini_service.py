from app.config.settings import settings
from app.models.schemas import ChatHistoryMessage

class GeminiService:
    def __init__(self) -> None:
        if not settings.is_api_key_set:
            raise ValueError("GEMINI_API_KEY is not set in .env file")

        import google.generativeai as genai

        genai.configure(api_key=settings.GEMINI_API_KEY)
        self.model = genai.GenerativeModel(settings.GEMINI_MODEL)
        
    def generate_response(self, user_message: str, history: list[ChatHistoryMessage] | None = None) -> str:
        """
        Send user message to Gemini and get response
        """
        try:
            gemini_history = []
            for item in history or []:
                if item.role == "system":
                    continue
                gemini_history.append({
                    "role": "model" if item.role == "assistant" else "user",
                    "parts": [item.content],
                })

            # Start a chat session with the persisted conversation context.
            chat = self.model.start_chat(history=gemini_history)
            
            # Send the message
            response = chat.send_message(user_message)
            
            # Extract the text response
            return response.text
            
        except Exception as e:
            # Re-raise with a clear message
            raise Exception(f"Gemini API error: {str(e)}")

def generate_response(user_message: str, history: list[ChatHistoryMessage] | None = None) -> str:
    return GeminiService().generate_response(user_message, history)