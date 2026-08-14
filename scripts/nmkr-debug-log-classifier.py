#!/usr/bin/env python3
"""Classify recent debug-log errors without exposing log contents."""

from __future__ import annotations

import argparse
import datetime as dt
import os
import re
import sys

BLOCKING_PATTERN = re.compile(
    r"PHP (?:Fatal error|Parse error)|NMKR.*(?:Fatal|Error|Exception)",
    re.IGNORECASE,
)
WARNING_NOTICE_PATTERN = re.compile(r"PHP (?:Warning|Notice)", re.IGNORECASE)
SOURCE_LOCATION_PATTERN = re.compile(
    r"\bin\s+(.+?)\s+on line\s+\d+\s*$", re.IGNORECASE
)
VENDOR_SEGMENT_PATTERN = re.compile(r"(?:^|/)vendor(?:/|$)", re.IGNORECASE)
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


def is_qualifying(line: str, cutoff: dt.datetime, file_is_recent: bool) -> bool:
    timestamp = parse_timestamp(line)
    if timestamp is None:
        return file_is_recent
    return timestamp >= cutoff


def is_vendor_warning_or_notice(line: str) -> bool:
    location = SOURCE_LOCATION_PATTERN.search(line)
    if location is None:
        return False
    normalized_path = location.group(1).strip().replace("\\", "/")
    return VENDOR_SEGMENT_PATTERN.search(normalized_path) is not None


def classify_matches(path: str, lookback_minutes: int, now: dt.datetime) -> int:
    cutoff = now - dt.timedelta(minutes=lookback_minutes)
    file_is_recent = dt.datetime.fromtimestamp(
        os.stat(path, follow_symlinks=False).st_mtime, dt.timezone.utc
    ) >= cutoff

    lines = tail_lines(path, 300)

    vendor_diagnostic = False
    for line in lines:
        blocking = BLOCKING_PATTERN.search(line) is not None
        warning_or_notice = WARNING_NOTICE_PATTERN.search(line) is not None
        if not blocking and not warning_or_notice:
            continue
        if not is_qualifying(line, cutoff, file_is_recent):
            continue
        if blocking or not is_vendor_warning_or_notice(line):
            return 1
        vendor_diagnostic = True
    return 3 if vendor_diagnostic else 0


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
        return classify_matches(
            args.path, args.lookback_minutes, dt.datetime.now(dt.timezone.utc)
        )
    except (OSError, OverflowError, ValueError):
        return 2


if __name__ == "__main__":
    sys.exit(main())
