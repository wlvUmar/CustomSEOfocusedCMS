import os

EXTENSIONS = {".html", ".php", ".js", ".sql", ".css"}
SPECIAL_FILES = {".htaccess"}
OUTPUT_FILE = "all_source_files.txt"

with open(OUTPUT_FILE, "w", encoding="utf-8") as out:
    for root, dirs, files in os.walk("."):
        dirs[:] = [d for d in dirs if d not in {"node_modules", "vendor", ".git"}]

        for file in sorted(files):
            _, ext = os.path.splitext(file)

            if ext in EXTENSIONS or file in SPECIAL_FILES:
                path = os.path.join(root, file)

                out.write(f"# {file}\n")
                out.write(f"# path: {path}\n\n")

                try:
                    with open(path, "r", encoding="utf-8", errors="replace") as f:
                        out.write(f.read())
                except Exception as e:
                    out.write(f"# ERROR reading file: {e}\n")

                out.write("\n\n" + "=" * 80 + "\n\n")
