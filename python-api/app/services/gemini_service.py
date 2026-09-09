from app.config.settings import settings

class GeminiService:
    def __init__(self) -> None:
        if not settings.is_api_key_set:
            raise ValueError("GEMINI_API_KEY is not set in .env file")

        import google.generativeai as genai

        genai.configure(api_key=settings.GEMINI_API_KEY)
        self.model = genai.GenerativeModel(settings.GEMINI_MODEL)
        
    def generate_response(self, user_message: str) -> str:
        """
        Send user message to Gemini and get response
        """
        try:
            # Start a chat session
            chat = self.model.start_chat(history=[])
            
            # Send the message
            response = chat.send_message(user_message)
            
            # Extract the text response
            return response.text
            
        except Exception as e:
            # Re-raise with a clear message
            raise Exception(f"Gemini API error: {str(e)}")

def generate_response(user_message: str) -> str:
    return GeminiService().generate_response(user_message)