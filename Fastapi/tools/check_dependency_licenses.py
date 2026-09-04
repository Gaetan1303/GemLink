"""Fail CI when installed Python packages advertise forbidden licenses."""

import json
import subprocess
import sys


FORBIDDEN = ("AGPL", "GPL", "SSPL", "NON-COMMERCIAL", "NON COMMERCIAL", "RESEARCH ONLY")


def main() -> int:
    result = subprocess.run(
        [sys.executable, "-m", "piplicenses", "--format=json"],
        check=True,
        capture_output=True,
        text=True,
    )
    packages = json.loads(result.stdout)
    rejected = [
        f"{package['Name']} ({package['License']})"
        for package in packages
        if any(marker in package.get("License", "").upper() for marker in FORBIDDEN)
    ]
    if rejected:
        print("Forbidden dependency licenses:", *rejected, sep="\n- ", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
