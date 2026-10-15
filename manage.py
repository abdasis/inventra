#!/usr/bin/env python3
"""
Inventra Management CLI
Helper script for running development server, database migrations, tests, and docker commands.
Written in Python per system requirements.
"""

import sys
import subprocess
import argparse
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent

def run_serve(args):
    """Start local PHP development server"""
    port = args.port
    host = args.host
    web_root = BASE_DIR / "apps" / "web" / "public"
    print(f"[*] Starting Inventra server on http://{host}:{port}")
    print(f"[*] Serving web & api from: {web_root}")
    try:
        cmd = ["php", "-S", f"{host}:{port}", "-t", str(web_root)]
        subprocess.run(cmd, cwd=str(BASE_DIR))
    except KeyboardInterrupt:
        print("\n[+] Server stopped.")

def run_test(args):
    """Run test suite"""
    test_script = BASE_DIR / "packages" / "core" / "tests" / "run_tests.php"
    print(f"[*] Running Inventra unit tests: {test_script}")
    cmd = ["php", str(test_script)]
    res = subprocess.run(cmd, cwd=str(BASE_DIR))
    sys.exit(res.returncode)

def run_migrate(args):
    """Run database migrations and optional seeder"""
    migrate_script = BASE_DIR / "packages" / "database" / "migrate.php"
    cmd = ["php", str(migrate_script)]
    if args.seed:
        cmd.append("--seed")
    res = subprocess.run(cmd, cwd=str(BASE_DIR))
    sys.exit(res.returncode)

def run_docker(args):
    """Manage docker services"""
    action = args.action
    cmd = ["docker", "compose", action]
    if action == "up":
        cmd.append("-d")
    print(f"[*] Running: {' '.join(cmd)}")
    res = subprocess.run(cmd, cwd=str(BASE_DIR))
    sys.exit(res.returncode)

def main():
    parser = argparse.ArgumentParser(description="Inventra CLI Manager")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # serve
    p_serve = subparsers.add_parser("serve", help="Start PHP dev server")
    p_serve.add_argument("--host", default="127.0.0.1", help="Host binding (default: 127.0.0.1)")
    p_serve.add_argument("--port", type=int, default=8080, help="Port (default: 8080)")
    p_serve.set_defaults(func=run_serve)

    # test
    p_test = subparsers.add_parser("test", help="Run test suite")
    p_test.set_defaults(func=run_test)

    # migrate
    p_migrate = subparsers.add_parser("migrate", help="Apply SQL migrations")
    p_migrate.add_argument("--seed", action="store_true", help="Seed demo office assets")
    p_migrate.set_defaults(func=run_migrate)

    # docker
    p_docker = subparsers.add_parser("docker", help="Manage docker services (up, down, logs, ps)")
    p_docker.add_argument("action", choices=["up", "down", "logs", "ps", "restart"], help="Docker compose action")
    p_docker.set_defaults(func=run_docker)

    args = parser.parse_args()
    args.func(args)

if __name__ == "__main__":
    main()
