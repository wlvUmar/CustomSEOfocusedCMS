from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import paramiko
import os
import time
import threading
import hashlib
import logging
import subprocess
import json
import socket

print("=== Watcher Starting ===", flush=True)

# ===== CONFIG =====
REMOTE_IP = "192.168.100.7"
USERNAME = "umar" 
PASSWORD = "getout04"

# Two sync directories
SYNC_DIRS = [
    {
        "name": "PHP CMS",
        "local": ".",
        "remote": "/home/umar/appliances"
    },
    {
        "name": "ReviewRequestBot",
        "local": "../ReviewRequestBot",
        "remote": "/home/umar/ReviewRequestBot"
    }
]

DEBOUNCE = 1.0
LOG_FILE = "watcher.log"
# Base local paths for git status cwd per sync dir (remaining-bugs #5 fix for ../ReviewRequestBot)
LOCAL_PATH = os.path.abspath(SYNC_DIRS[0]["local"])
LOCAL_PATHS = {d["local"]: os.path.abspath(d["local"]) for d in SYNC_DIRS}

# ===== IGNORE RULES =====
IGNORE_DIRS = {".git", "__pycache__", "node_modules", ".idea", ".vscode", "venv", ".venv"}
# Expanded to prevent secret sync: .env, sql dumps, logs, gsc tokens, caches
IGNORE_FILES = {
    "watcher.log", "watcher.py", ".htaccess",
    ".env",
    "kuplyuta_db.sql",
    "tracking_audit.log",
    "openrouter_models.json", "gsc_token.lock",
}

GIT_CHECK_INTERVAL = 5  # Check git status every 5 seconds

# ===== LOGGING =====
logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s"
)
console = logging.StreamHandler()
console.setLevel(logging.INFO)
console.setFormatter(logging.Formatter("%(asctime)s | %(levelname)s | %(message)s"))
logging.getLogger('').addHandler(console)

# ===== SSH =====
transport = None
sftp = None
ssh_lock = threading.Lock()

def connect_ssh(retry=True):
    global transport, sftp
    while True:
        try:
            logging.info("Connecting to SSH server...")
            # Clean up old connections first
            try:
                if sftp:
                    sftp.close()
            except Exception:
                pass
            try:
                if transport:
                    transport.close()
            except Exception:
                pass

            transport = paramiko.Transport((REMOTE_IP, 22))
            transport.connect(username=USERNAME, password=PASSWORD)
            transport.set_keepalive(30)
            sftp = paramiko.SFTPClient.from_transport(transport)
            logging.info("SSH connected successfully")
            return
        except Exception as e:
            logging.error(f"SSH connection failed: {e}")
            if not retry:
                print(f"FATAL: SSH connection failed: {e}")
                exit(1)
            logging.info("Retrying SSH connection in 10 seconds...")
            time.sleep(10)

def is_ssh_alive():
    """Check if the SSH transport is still active."""
    return transport is not None and transport.is_active()

def ensure_ssh():
    """Reconnect SSH if the connection is dead. Thread-safe."""
    if not is_ssh_alive():
        with ssh_lock:
            # Double-check after acquiring lock
            if not is_ssh_alive():
                logging.warning("SSH connection lost — reconnecting...")
                connect_ssh(retry=True)

def is_recoverable_ssh_error(exc):
    """Return True for transient SSH/network errors that should trigger reconnect+retry."""
    if isinstance(exc, (EOFError, socket.timeout, TimeoutError, ConnectionError, OSError)):
        return True
    if isinstance(exc, (paramiko.SSHException, paramiko.NoValidConnectionsError)):
        return True

    err = str(exc).lower()
    recoverable_markers = (
        "socket",
        "closed",
        "connection",
        "timed out",
        "timeout",
        "eof",
        "unable to connect",
        "no existing session",
        "error reading ssh protocol banner",
        "10054",
        "10060",
    )
    return any(marker in err for marker in recoverable_markers)

connect_ssh(retry=True)

# ===== STATE =====
file_state = {}  # {path: hash}
pending_changes = []  # [(path, sync_dir), ...]
git_tracked = set()
uploaded_git_files = set()
lock = threading.Lock()

# ===== HELPERS =====
def is_ignored(path):
    parts = path.replace("\\", "/").split("/")
    basename = os.path.basename(path)

    if basename in IGNORE_FILES:
        return True
    # Secret patterns: never sync env/SQL/logs/caches even if not listed explicitly
    sensitive_suffixes = (".env", ".sql", ".log", ".sqlite", ".db")
    sensitive_names = ("gsc_token.lock", "openrouter_models.json", "google-service-account.json")
    if basename in sensitive_names:
        return True
    if any(basename.endswith(suf) for suf in sensitive_suffixes):
        return True
    # Block storage cache dirs and logs dir
    if "storage/gsc_cache" in path.replace("\\", "/") or "storage/gsc_token" in path.replace("\\", "/"):
        return True
    if "/logs/" in path.replace("\\", "/") or path.replace("\\", "/").endswith("/logs"):
        return True
    if "/beta/" in path.replace("\\", "/"):
        return True

    return any(p in IGNORE_DIRS for p in parts)

def file_hash(path):
    try:
        with open(path, "rb") as f:
            return hashlib.md5(f.read()).hexdigest()
    except:
        return None

def ensure_dir(path):
    parts = path.split("/")
    cur = ""
    for p in parts:
        if not p:
            continue
        cur += "/" + p
        try:
            sftp.stat(cur)
        except:
            try:
                sftp.mkdir(cur)
            except:
                pass

def upload_file(path, sync_dir):
    max_retries = 3
    for attempt in range(1, max_retries + 1):
        try:
            ensure_ssh()

            rel = os.path.relpath(os.path.abspath(path), os.path.abspath(sync_dir["local"]))
            remote_file = os.path.join(sync_dir["remote"], rel).replace("\\", "/")

            ensure_dir(os.path.dirname(remote_file))
            sftp.put(path, remote_file)

            logging.info(f"[{sync_dir['name']}] Uploaded: {rel}")
            print(f"[{sync_dir['name']}] Uploaded: {rel}")
            return  # success

        except Exception as e:
            if is_recoverable_ssh_error(e):
                logging.warning(f"[{sync_dir['name']}] SSH/network error on attempt {attempt}/{max_retries}: {e}")
                connect_ssh(retry=True)
            else:
                logging.error(f"[{sync_dir['name']}] Upload failed {path}: {e}")
                return  # Non-network error, don't retry

    logging.error(f"[{sync_dir['name']}] Upload failed after {max_retries} attempts: {path}")

# ===== GIT HELPERS =====
def get_git_status():
    """Get list of modified and staged files from git status per SYNC_DIR"""
    changed = set()
    try:
        creationflags = 0
        if hasattr(subprocess, 'CREATE_NO_WINDOW'):
            creationflags = subprocess.CREATE_NO_WINDOW
        elif hasattr(subprocess, 'DETACHED_PROCESS'):
            creationflags = subprocess.DETACHED_PROCESS
        for sync_dir in SYNC_DIRS:
            cwd = os.path.abspath(sync_dir["local"])
            if not os.path.isdir(os.path.join(cwd, ".git")):
                # Only run git status where .git exists; also allow "." root
                if sync_dir["local"] != "." and not os.path.exists(os.path.join(cwd, ".git")):
                    continue
            try:
                result = subprocess.run(
                    ["git", "status", "--porcelain"],
                    cwd=cwd,
                    capture_output=True,
                    text=True,
                    timeout=5,
                    creationflags=creationflags
                )
            except Exception as e:
                logging.warning(f"Git status failed for {sync_dir['name']}: {e}")
                continue
            if result.returncode != 0:
                logging.warning(f"Git status failed for {sync_dir['name']}: {result.stderr}")
                continue
            for line in result.stdout.strip().split('\n'):
                if not line or len(line) < 3:
                    continue
                status = line[:2]
                filename = line[3:].strip()
                if status[0] in ['M', 'A', 'D', '?'] or status[1] in ['M', 'D']:
                    filepath = os.path.join(cwd, filename)
                    if os.path.exists(filepath):
                        changed.add(filepath)
        return changed
    except Exception as e:
        logging.error(f"Git status check failed: {e}")
        return set()

def check_git_changes():
    """Periodically check git status and queue changed files"""
    last_git_files = set()
    
    while True:
        try:
            time.sleep(GIT_CHECK_INTERVAL)
            
            changed = get_git_status()
            
            # Only add NEW changes (files not previously detected or uploaded)
            new_changes = (changed - last_git_files) - uploaded_git_files
            
            if new_changes:
                logging.info(f"Git detected {len(new_changes)} changes")
                with lock:
                    for filepath in new_changes:
                        if not is_ignored(filepath):
                            matched = next((d for d in SYNC_DIRS if os.path.abspath(filepath).startswith(os.path.abspath(d["local"]))), SYNC_DIRS[0])
                            pending_changes.append((filepath, matched))
                            git_tracked.add(filepath)
                            try:
                                rel = os.path.relpath(filepath, os.path.abspath(matched["local"]))
                            except:
                                rel = filepath
                            logging.info(f"Git queued [{matched['name']}]: {rel}")
            
            last_git_files = changed
        except Exception as e:
            logging.error(f"Error in check_git_changes: {e}", exc_info=True)
            time.sleep(5)

# ===== BATCH ENGINE =====
def process_changes():
    while True:
        try:
            time.sleep(1)

            with lock:
                if not pending_changes:
                    continue
                batch = list(pending_changes)
                pending_changes.clear()

            for path, sync_dir in batch:
                if is_ignored(path):
                    continue

                if not os.path.exists(path):
                    continue

                new_hash = file_hash(path)
                old_hash = file_state.get(path)

                if new_hash == old_hash:
                    continue

                file_state[path] = new_hash
                logging.info(f"[{sync_dir['name']}] Uploading: {os.path.relpath(os.path.abspath(path), os.path.abspath(sync_dir['local']))}")
                upload_file(path, sync_dir)
        except Exception as e:
            logging.error(f"Error in process_changes: {e}", exc_info=True)
            time.sleep(5)

# ===== WATCHER =====
class Handler(FileSystemEventHandler):
    def __init__(self, sync_dir):
        self.sync_dir = sync_dir

    def on_modified(self, event):
        if event.is_directory:
            return
        if is_ignored(event.src_path):
            return

        with lock:
            pending_changes.append((event.src_path, self.sync_dir))

    def on_created(self, event):
        self.on_modified(event)

# ===== INITIAL SCAN =====
def initial_scan():
    for sync_dir in SYNC_DIRS:
        local_path = sync_dir["local"]
        if not os.path.exists(local_path):
            logging.warning(f"[{sync_dir['name']}] Path does not exist: {local_path}")
            continue
        
        logging.info(f"[{sync_dir['name']}] Initial scan starting...")
        for root, dirs, files in os.walk(local_path):
            # skip ignored dirs early
            dirs[:] = [d for d in dirs if d not in IGNORE_DIRS]

            for f in files:
                path = os.path.join(root, f)
                if is_ignored(path):
                    continue
                file_state[path] = file_hash(path)
        
        logging.info(f"[{sync_dir['name']}] Initial scan done")

initial_scan()

observer = Observer()
for sync_dir in SYNC_DIRS:
    local_path = sync_dir["local"]
    if os.path.exists(local_path):
        observer.schedule(Handler(sync_dir), local_path, recursive=True)
        logging.info(f"[{sync_dir['name']}] Watching: {os.path.abspath(local_path)}")
observer.start()

threading.Thread(target=process_changes, daemon=True).start()

logging.info("Watcher started - monitoring file system")

try:
    while True:
        time.sleep(1)
        # Check if observer is still alive
        if not observer.is_alive():
            logging.error("Observer thread died, restarting...")
            observer.start()
except KeyboardInterrupt:
    logging.info("Keyboard interrupt, shutting down...")
except Exception as e:
    logging.error(f"Main loop error: {e}", exc_info=True)
finally:
    observer.stop()
    sftp.close()
    transport.close()
    observer.join()
    logging.info("Watcher stopped")