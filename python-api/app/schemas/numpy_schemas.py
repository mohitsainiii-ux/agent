from typing import Any, Literal, Optional

from pydantic import BaseModel, Field, field_validator

ALLOWED_OPERATIONS = (
    "create",
    "sum",
    "mean",
    "min",
    "max",
    "std",
    "sort",
    "filter",
    "reshape",
    "info",
    "abs",
    "square",
)

FILTER_CONDITIONS = ("gt", "gte", "lt", "lte", "eq", "ne")


class NumpyOptions(BaseModel):
    precision: int = Field(default=2, ge=0, le=10)
    allow_negative: bool = True
    allow_decimal: bool = True
    max_values: int = Field(default=10000, ge=1, le=100000)
    reshape_rows: Optional[int] = Field(default=None, ge=1)
    reshape_cols: Optional[int] = Field(default=None, ge=1)
    filter_condition: Optional[Literal["gt", "gte", "lt", "lte", "eq", "ne"]] = "gt"
    filter_value: Optional[float] = None


class NumpyProcessRequest(BaseModel):
    operation: str
    data: list[Any]
    options: NumpyOptions = Field(default_factory=NumpyOptions)

    @field_validator("operation")
    @classmethod
    def validate_operation(cls, value: str) -> str:
        operation = value.strip().lower()
        if operation not in ALLOWED_OPERATIONS:
            raise ValueError("Invalid operation. Choose a supported NumPy operation.")
        return operation


class NumpyArrayRequest(BaseModel):
    data: list[Any]
    options: NumpyOptions = Field(default_factory=NumpyOptions)


class NumpyReshapeRequest(BaseModel):
    data: list[Any]
    rows: int = Field(..., ge=1)
    cols: int = Field(..., ge=1)
    options: NumpyOptions = Field(default_factory=NumpyOptions)
