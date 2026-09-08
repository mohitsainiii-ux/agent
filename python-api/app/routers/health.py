from fastapi import APIRouter

router = APIRouter()


@router.get("/health")
def health() -> dict:
    return {
        "success": True,
        "status": "ok",
        "service": "python-api",
        "modules": ["numpy"],
    }
