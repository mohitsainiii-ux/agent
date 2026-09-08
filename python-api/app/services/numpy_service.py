import math
from typing import Any

import numpy as np

from app.core.errors import ProcessingError
from app.schemas.numpy_schemas import NumpyOptions


class NumpyService:
    def process(self, operation: str, data: list[Any], options: NumpyOptions) -> dict[str, Any]:
        array = self.create_array(data, options)

        handlers = {
            "create": self._create,
            "sum": self._sum,
            "mean": self._mean,
            "min": self._min,
            "max": self._max,
            "std": self._std,
            "sort": self._sort,
            "filter": self._filter,
            "reshape": self._reshape,
            "info": self._info,
            "abs": self._abs,
            "square": self._square,
        }

        handler = handlers.get(operation)
        if handler is None:
            raise ProcessingError("Invalid operation.")

        payload = handler(array, options)
        payload["success"] = True
        payload["operation"] = operation
        payload["info"] = self._array_info(array if operation != "reshape" else np.array(payload.get("result", array)))
        return payload

    def create_array(self, data: list[Any], options: NumpyOptions) -> np.ndarray:
        if data is None or (isinstance(data, list) and len(data) == 0):
            raise ProcessingError("Input is empty. Enter at least one number.")

        if not isinstance(data, list):
            raise ProcessingError("Input must be a list of numbers.")

        if len(data) > options.max_values:
            raise ProcessingError(f"Too many values. Maximum allowed is {options.max_values}.")

        numbers: list[float] = []
        for index, item in enumerate(data):
            if isinstance(item, bool) or not isinstance(item, (int, float)):
                raise ProcessingError(f"Non-numeric value found at position {index + 1}.")

            value = float(item)
            if not math.isfinite(value):
                raise ProcessingError(f"Value at position {index + 1} must be finite.")
            if not options.allow_decimal and not float(value).is_integer():
                raise ProcessingError("Decimal numbers are not allowed.")
            if not options.allow_negative and value < 0:
                raise ProcessingError("Negative numbers are not allowed.")
            numbers.append(value)

        try:
            return np.array(numbers, dtype=float)
        except Exception:
            raise ProcessingError("Could not convert the input into a numeric array.") from None

    def reshape(self, data: list[Any], rows: int, cols: int, options: NumpyOptions) -> dict[str, Any]:
        options.reshape_rows = rows
        options.reshape_cols = cols
        return self.process("reshape", data, options)

    def _create(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_list(array.tolist(), options.precision)}

    def _sum(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_number(float(np.sum(array)), options.precision)}

    def _mean(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_number(float(np.mean(array)), options.precision)}

    def _min(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_number(float(np.min(array)), options.precision)}

    def _max(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_number(float(np.max(array)), options.precision)}

    def _std(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_number(float(np.std(array)), options.precision)}

    def _sort(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_list(np.sort(array).tolist(), options.precision)}

    def _abs(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_list(np.abs(array).tolist(), options.precision)}

    def _square(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {"result": self._round_list(np.square(array).tolist(), options.precision)}

    def _filter(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        if options.filter_value is None:
            raise ProcessingError("A filter value is required.")

        condition = options.filter_condition or "gt"
        value = float(options.filter_value)
        masks = {
            "gt": array > value,
            "gte": array >= value,
            "lt": array < value,
            "lte": array <= value,
            "eq": array == value,
            "ne": array != value,
        }
        mask = masks.get(condition)
        if mask is None:
            raise ProcessingError("Invalid filter condition.")

        filtered = array[mask]
        return {"result": self._round_list(filtered.tolist(), options.precision)}

    def _reshape(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        rows = options.reshape_rows
        cols = options.reshape_cols
        if rows is None or cols is None:
            raise ProcessingError("Reshape requires both rows and columns.")
        if rows * cols != array.size:
            raise ProcessingError(
                f"Invalid reshape dimensions. Array size is {int(array.size)}, but {rows} x {cols} requires {rows * cols} values."
            )
        try:
            reshaped = array.reshape((rows, cols))
        except Exception:
            raise ProcessingError("Invalid reshape dimensions.") from None
        return {"result": self._round_nested(reshaped.tolist(), options.precision)}

    def _info(self, array: np.ndarray, options: NumpyOptions) -> dict[str, Any]:
        return {
            "result": self._array_info(array),
            "array": self._round_list(array.tolist(), options.precision),
        }

    def _array_info(self, array: np.ndarray) -> dict[str, Any]:
        return {
            "shape": list(array.shape),
            "size": int(array.size),
            "ndim": int(array.ndim),
            "dtype": str(array.dtype),
        }

    def _round_number(self, value: float, precision: int) -> float:
        return round(float(value), precision)

    def _round_list(self, values: list[Any], precision: int) -> list[float]:
        return [self._round_number(float(item), precision) for item in values]

    def _round_nested(self, values: list[Any], precision: int) -> list[Any]:
        rounded: list[Any] = []
        for item in values:
            if isinstance(item, list):
                rounded.append(self._round_nested(item, precision))
            else:
                rounded.append(self._round_number(float(item), precision))
        return rounded
