#!/usr/bin/env python3
"""Classify recent debug-log errors without exposing log contents."""

from __future__ import annotations

import argparse
import datetime as dt
import os
import re
import sys
ERROR_PATTERN = re.compile(
    r"PHP (?:Fatal error|Parse error|Warning|Notice)|NMKR.*(?:Fatal|Error|Exception)",
    re.IGNORECASE,
)
TIMESTAMP_PATTERN = re.compile(r"^\[([^]]+)]")
TIMESTAMP_FORMATS = (
    "%d-%b-%Y %H:%M:%S %Z",
    "%d-%b-%Y %H:%M:%S",
    "%Y-%m-%d %H:%M:%S %Z",
    "%Y-%m-%d %H:%M:%S",
)


def parse_timestamp(line: str) -> dt.datetime | None:
    match = TIMESTAMP_PATTERN.match(line.lstrip())
    if match is None:
        return None

    value = match.group(1)
    for time_format in TIMESTAMP_FORMATS:
        try:
            parsed = dt.datetime.strptime(value, time_format)
        except ValueError:
            continue
        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=dt.timezone.utc)
        return parsed.astimezone(dt.timezone.utc)
    return None


def has_failing_match(path: str, lookback_minutes: int, now: dt.datetime) -> bool:
    cutoff = now - dt.timedelta(minutes=lookback_minutes)
    file_is_recent = dt.datetime.fromtimestamp(
        os.stat(path, follow_symlinks=False).st_mtime, dt.timezone.utc
    ) >= cutoff

    lines = tail_lines(path, 300)

    for line in lines:
        if ERROR_PATTERN.search(line) is None:
            continue
        timestamp = parse_timestamp(line)
        if timestamp is None:
            if file_is_recent:
                return True
        elif timestamp >= cutoff:
            return True
    return False


def tail_lines(path: str, limit: int) -> list[str]:
    """Read at most the final ``limit`` newline-delimited logical lines."""
    with open(path, "rb") as log_file:
        log_file.seek(0, os.SEEK_END)
        position = log_file.tell()
        chunks: list[bytes] = []
        newline_count = 0
        trailing_newline = False
        while position > 0 and newline_count <= limit:
            size = min(8192, position)
            position -= size
            log_file.seek(position)
            chunk = log_file.read(size)
            chunks.append(chunk)
            newline_count += chunk.count(b"\n")
            if len(chunks) == 1:
                trailing_newline = chunk.endswith(b"\n")

    raw_lines = b"".join(reversed(chunks)).splitlines(keepends=True)
    if trailing_newline and raw_lines and raw_lines[-1] == b"":
        raw_lines.pop()
    return [line.decode("utf-8", "replace") for line in raw_lines[-limit:]]


def main() -> int:
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument("path")
    parser.add_argument("lookback_minutes", type=int)
    args = parser.parse_args()

    if args.lookback_minutes <= 0:
        return 2

    try:
        return int(
            has_failing_match(
                args.path, args.lookback_minutes, dt.datetime.now(dt.timezone.utc)
            )
        )
    except (OSError, OverflowError, ValueError):
        return 2


if __name__ == "__main__":
    sys.exit(main())
