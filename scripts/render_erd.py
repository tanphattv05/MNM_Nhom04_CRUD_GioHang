"""Render the verified SQL Server metadata as report-ready PNG, SVG and PDF.

Optional documentation tool: python scripts/render_erd.py (requires matplotlib).
Does not connect to or change the database.
"""
import json
import re
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import Circle, Rectangle

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "docs" / "diagrams"
schema = json.loads((OUTPUT / "database-schema.json").read_text(encoding="utf-8"))

plt.rcParams.update({"font.family": "DejaVu Sans", "svg.fonttype": "none", "pdf.fonttype": 42})
fig, ax = plt.subplots(figsize=(20.7, 13.8))
fig.subplots_adjust(left=0, right=1, top=1, bottom=0)
ax.set_xlim(0, 2300)
ax.set_ylim(1530, 0)
ax.axis("off")
fig.set_facecolor("white")

INK = "#1d3048"
MUTED = "#53647a"
LINE = "#b6c5d7"
BLUE = "#215b99"
ROW_HEIGHT = 40
HEADER_HEIGHT = 56
WIDTH = 570
positions = {
    "THANH_VIEN": (60, 180), "GIO_HANG": (860, 180), "QTV": (1660, 180),
    "MUA": (60, 970), "SACH": (860, 970), "LOAI_SACH": (1660, 970),
}
titles = {"THANH_VIEN": "Thành viên", "GIO_HANG": "Giỏ hàng", "QTV": "Quản trị viên",
          "MUA": "Chi tiết giỏ hàng", "SACH": "Sách", "LOAI_SACH": "Loại sách"}
expected_edges = {
    ("THANH_VIEN", "GIO_HANG"), ("QTV", "GIO_HANG"),
    ("THANH_VIEN", "MUA"), ("GIO_HANG", "MUA"),
    ("SACH", "MUA"), ("LOAI_SACH", "SACH"),
}
actual_edges = {(r["parent_table"], r["child_table"]) for r in schema["relationships"]}
if actual_edges != expected_edges or set(schema["tables"]) != set(positions):
    raise ValueError("Metadata differs from this six-table layout; review the relationships first.")

ax.text(60, 56, "ERD · WEBSITE BÁN SÁCH", fontsize=23, fontweight="bold", color=INK)
ax.text(60, 100, "Nhóm 04 · Lớp DA23TTA  |  " + schema["database"] + " · Microsoft SQL Server", fontsize=12, color=MUTED)
ax.text(2230, 100, "Đối chiếu schema: " + schema["verified_on"], fontsize=10, color=MUTED, ha="right")

for table, (x, y) in positions.items():
    columns = schema["tables"][table]
    height = HEADER_HEIGHT + len(columns) * ROW_HEIGHT
    ax.add_patch(Rectangle((x, y), WIDTH, height, facecolor="white", edgecolor=LINE, linewidth=1.2, zorder=3))
    ax.add_patch(Rectangle((x, y), WIDTH, HEADER_HEIGHT, facecolor="#eaf2fc", edgecolor=LINE, linewidth=1.2, zorder=3))
    ax.text(x + 16, y + 32, table, fontsize=12, fontweight="bold", color=BLUE, va="center", zorder=5)
    ax.text(x + WIDTH - 16, y + 32, titles[table], fontsize=9, color=MUTED, ha="right", va="center", zorder=5)
    for index, column in enumerate(columns):
        row_y = y + HEADER_HEIGHT + index * ROW_HEIGHT
        if index % 2 == 1:
            ax.add_patch(Rectangle((x + 1, row_y), WIDTH - 2, ROW_HEIGHT, facecolor="#f7f9fc", edgecolor="none", zorder=3))
        keys = []
        if column["is_primary_key"]: keys.append("PK")
        if column["is_foreign_key"]: keys.append("FK")
        if column["is_unique"]: keys.append("UK")
        center_y = row_y + ROW_HEIGHT / 2
        ax.text(x + 12, center_y, ",".join(keys), fontsize=7.7, color=BLUE, va="center", fontweight="bold", zorder=5)
        ax.text(x + 78, center_y, column["name"], fontsize=8.7, fontfamily="DejaVu Sans Mono", color=INK, va="center", zorder=5)
        ax.text(x + 405, center_y, column["sql_type"], fontsize=8.2, fontfamily="DejaVu Sans Mono", color=MUTED, va="center", zorder=5)
        ax.text(x + WIDTH - 10, center_y, "NULL" if column["is_nullable"] else "NN", fontsize=7.3, color=MUTED, ha="right", va="center", zorder=5)


def cap(point, outward, kind):
    """Crow's-foot cardinality at the boundary of its own entity."""
    px, py = point
    nx, ny = outward
    tx, ty = -ny, nx

    def bar(distance):
        ax.plot([px + nx * distance - tx * 10, px + nx * distance + tx * 10],
                [py + ny * distance - ty * 10, py + ny * distance + ty * 10], color=BLUE, linewidth=1.3, zorder=7)

    if kind == "many":
        for offset in (-11, 0, 11):
            ax.plot([px + tx * offset, px + nx * 26], [py + ty * offset, py + ny * 26], color=BLUE, linewidth=1.3, zorder=7)
        ax.add_patch(Circle((px + nx * 43, py + ny * 43), 6, facecolor="white", edgecolor=BLUE, linewidth=1.3, zorder=7))
    else:
        bar(14)
        if kind == "optional":
            ax.add_patch(Circle((px + nx * 32, py + ny * 32), 6, facecolor="white", edgecolor=BLUE, linewidth=1.3, zorder=7))
        else:
            bar(28)


def relation(parent, child, points, label, label_at):
    row = next(r for r in schema["relationships"] if r["parent_table"] == parent and r["child_table"] == child)
    identifying = any(c["name"] == row["child_column"] and c["is_primary_key"] for c in schema["tables"][child])
    ax.plot([p[0] for p in points], [p[1] for p in points], color=BLUE, linewidth=1.4,
            linestyle="-" if identifying else (0, (5, 4)), zorder=1)
    for endpoint, neighbor, kind in ((points[0], points[1], "optional" if row["optional_parent"] else "one"),
                                     (points[-1], points[-2], "many")):
        dx, dy = neighbor[0] - endpoint[0], neighbor[1] - endpoint[1]
        length = (dx * dx + dy * dy) ** 0.5
        cap(endpoint, (dx / length, dy / length), kind)
    ax.text(*label_at, label, fontsize=10, color=BLUE, ha="center", va="center",
            bbox={"facecolor": "white", "edgecolor": "none", "pad": 4}, zorder=8)


relation("THANH_VIEN", "GIO_HANG", [(630, 330), (860, 330)], "Sở hữu", (745, 304))
relation("QTV", "GIO_HANG", [(1660, 330), (1430, 330)], "Xử lý / duyệt", (1545, 304))
relation("THANH_VIEN", "MUA", [(180, 556), (180, 970)], "Tham gia mua", (180, 650))
relation("GIO_HANG", "MUA", [(1110, 516), (1110, 750), (475, 750), (475, 970)], "Gồm các dòng sách", (790, 750))
relation("SACH", "MUA", [(860, 1080), (630, 1080)], "Có trong", (745, 1054))
relation("LOAI_SACH", "SACH", [(1660, 1045), (1430, 1045)], "Phân loại", (1545, 1019))

ax.plot([60, 2230], [1340, 1340], color=LINE, linewidth=1)
ax.text(60, 1383, "PK: khóa chính  ·  FK: khóa ngoại  ·  UK: duy nhất  ·  NN: NOT NULL  ·  NULL: có thể để trống", fontsize=10, color=INK)
ax.text(60, 1423, "Bội số: || = 1; o| = 0..1; o< = 0..N. Nét liền: FK thuộc khóa chính; nét đứt: FK không thuộc khóa chính.", fontsize=9.5, color=MUTED)
ax.text(60, 1463, "Giỏ mới luôn có chủ và ít nhất một dòng sách theo nghiệp vụ; schema cũ vẫn cho phép chủ giỏ NULL và giỏ chưa có dòng MUA.", fontsize=9.5, color=MUTED)
for extension in ("png", "svg", "pdf"):
    target = OUTPUT / ("database-erd." + extension)
    fig.savefig(target, dpi=180, facecolor="white")
    print(target.relative_to(ROOT))
plt.close(fig)

# Keep the editable text diagram consistent with the exported image.
mermaid = ["erDiagram", "    direction LR"]
labels = {("THANH_VIEN", "GIO_HANG"): "sở hữu", ("QTV", "GIO_HANG"): "xử lý",
          ("THANH_VIEN", "MUA"): "mua", ("GIO_HANG", "MUA"): "gồm",
          ("SACH", "MUA"): "xuất hiện", ("LOAI_SACH", "SACH"): "phân loại"}
for relationship in schema["relationships"]:
    parent, child = relationship["parent_table"], relationship["child_table"]
    identifying = any(c["name"] == relationship["child_column"] and c["is_primary_key"] for c in schema["tables"][child])
    cardinality = ("o|" if relationship["optional_parent"] else "||") + ("--" if identifying else "..") + "o{"
    mermaid.append(f'    {parent} {cardinality} {child} : "{labels[(parent, child)]}"')
for table, columns in schema["tables"].items():
    mermaid.append("\n    " + table + " {")
    for column in columns:
        keys = [label for key, label in (("is_primary_key", "PK"), ("is_foreign_key", "FK"), ("is_unique", "UK")) if column[key]]
        key_text = (" " + ",".join(keys)) if keys else ""
        null_text = "NULL" if column["is_nullable"] else "NOT NULL"
        mermaid.append(f'        {column["type"]} {column["name"]}{key_text} "{column["sql_type"]}; {null_text}"')
    mermaid.append("    }")
source = "\n".join(mermaid) + "\n"
(OUTPUT / "database-erd.mmd").write_text(source, encoding="utf-8")
document = OUTPUT / "database-erd.md"
contents = document.read_text(encoding="utf-8")
contents, replaced = re.subn(r"```mermaid\n.*?```", "```mermaid\n" + source + "```", contents, count=1, flags=re.DOTALL)
if replaced != 1:
    raise ValueError("Expected one Mermaid block in database-erd.md")
document.write_text(contents, encoding="utf-8")
print("Updated Mermaid source and database-erd.md")
