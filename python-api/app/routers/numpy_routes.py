from fastapi import APIRouter

from app.schemas.numpy_schemas import NumpyArrayRequest, NumpyProcessRequest, NumpyReshapeRequest
from app.services.numpy_service import NumpyService

router = APIRouter(prefix="/numpy", tags=["numpy"])
service = NumpyService()


@router.post("/process")
def process(payload: NumpyProcessRequest) -> dict:
    return service.process(payload.operation, payload.data, payload.options)


@router.post("/array")
def create_array(payload: NumpyArrayRequest) -> dict:
    return service.process("create", payload.data, payload.options)


@router.post("/reshape")
def reshape(payload: NumpyReshapeRequest) -> dict:
    return service.reshape(payload.data, payload.rows, payload.cols, payload.options)
