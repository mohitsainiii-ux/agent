from fastapi import HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse


class ProcessingError(Exception):
    def __init__(self, message: str, status_code: int = 400):
        self.message = message
        self.status_code = status_code
        super().__init__(message)


async def processing_error_handler(_: Request, exc: ProcessingError) -> JSONResponse:
    return JSONResponse(
        status_code=exc.status_code,
        content={"success": False, "error": exc.message},
    )


async def http_error_handler(_: Request, exc: HTTPException) -> JSONResponse:
    detail = exc.detail
    if not isinstance(detail, str):
        detail = "The request could not be processed."
    return JSONResponse(
        status_code=exc.status_code,
        content={"success": False, "error": detail},
    )


async def validation_error_handler(_: Request, exc: RequestValidationError) -> JSONResponse:
    message = "Invalid request data. Check the numbers, operation, and options."
    errors = exc.errors()
    if errors:
        first = errors[0].get("msg")
        if isinstance(first, str) and first:
            cleaned = first.replace("Value error, ", "")
            if cleaned:
                message = cleaned
    return JSONResponse(status_code=400, content={"success": False, "error": message})


async def unhandled_error_handler(_: Request, __: Exception) -> JSONResponse:
    return JSONResponse(
        status_code=500,
        content={"success": False, "error": "A processing error occurred. Please check your input and try again."},
    )
