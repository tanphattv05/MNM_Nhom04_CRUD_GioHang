"""Export the Cart documentation diagrams as PNG/SVG (requires matplotlib)."""
from pathlib import Path
import textwrap

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import Circle, Ellipse, FancyArrowPatch, FancyBboxPatch, Rectangle

OUTPUT = Path(__file__).resolve().parents[1] / "docs" / "diagrams"
plt.rcParams.update({"font.family": "DejaVu Sans", "svg.fonttype": "none"})
INK, BLUE, MUTED, LINE = "#19334f", "#2563a3", "#586b80", "#c0cedd"


def canvas(width, height, title, subtitle, *, title_size=20, subtitle_size=10):
    fig, ax = plt.subplots(figsize=(width / 110, height / 110))
    fig.subplots_adjust(left=0, right=1, bottom=0, top=1)
    ax.set_xlim(0, width)
    ax.set_ylim(height, 0)
    ax.axis("off")
    fig.set_facecolor("white")
    ax.text(55, 54, title, color=INK, fontsize=title_size, fontweight="bold")
    ax.text(55, 90, subtitle, color=MUTED, fontsize=subtitle_size)
    return fig, ax


def save(fig, name):
    for extension in ("png", "svg"):
        target = OUTPUT / f"{name}.{extension}"
        fig.savefig(target, dpi=180, facecolor="white")
        if extension == "svg":
            contents = target.read_text(encoding="utf-8")
            target.write_text("\n".join(line.rstrip() for line in contents.splitlines()) + "\n", encoding="utf-8")
        print(target.relative_to(OUTPUT.parent.parent))
    plt.close(fig)


def box(ax, x, y, width, height, title, body):
    ax.add_patch(FancyBboxPatch((x, y), width, height, boxstyle="round,pad=0,rounding_size=12",
                              facecolor="#eff5fc", edgecolor=LINE, linewidth=1.2, zorder=3))
    ax.text(x + width / 2, y + 35, title, ha="center", color=BLUE, fontsize=12, fontweight="bold", zorder=4)
    ax.text(x + width / 2, y + height / 2 + 18, body, ha="center", va="center", color=INK,
            fontsize=10, linespacing=1.6, zorder=4)


def arrow(ax, points, label=None, label_at=None, dashed=False):
    for start, finish in zip(points[:-2], points[1:-1]):
        ax.plot([start[0], finish[0]], [start[1], finish[1]], color=BLUE, linewidth=1.4, zorder=1)
    ax.add_patch(FancyArrowPatch(points[-2], points[-1], arrowstyle="-|>", mutation_scale=15,
                                linewidth=1.4, color=BLUE, linestyle="--" if dashed else "-", zorder=2))
    if label:
        ax.text(*label_at, label, ha="center", va="center", fontsize=9, color=MUTED,
                bbox={"facecolor":"white", "edgecolor":"none", "pad":3}, zorder=5)


def architecture():
    fig, ax = canvas(2300, 880, "KIẾN TRÚC · MODULE GIỎ HÀNG", "Nhóm 04 · DA23TTA  |  Symfony · Twig · Doctrine DBAL · SQL Server")
    box(ax, 60, 310, 340, 160, "GIAO DIỆN", "Twig / Form / JavaScript\nDanh mục · Giỏ · Checkout")
    box(ax, 540, 310, 390, 160, "CONTROLLER", "HTTP · CSRF · Phân quyền\nThông báo / Redirect / Render")
    box(ax, 1080, 140, 390, 170, "SERVICE", "CartService: nghiệp vụ giỏ\nCheckoutService: kiểm tra người nhận")
    box(ax, 1630, 310, 390, 160, "TRUY XUẤT DỮ LIỆU", "BookRepository / CartRepository\nDoctrine DBAL Connection")
    box(ax, 1630, 620, 390, 130, "SYMFONY SESSION", "Giỏ nháp: items + nonce\nTách theo mã thành viên")
    box(ax, 1630, 140, 390, 110, "MICROSOFT SQL SERVER", "SACH · GIO_HANG · MUA")
    arrow(ax, [(400,390),(540,390)], "Request", (470,365))
    arrow(ax, [(930,345),(1000,345),(1000,225),(1080,225)])
    arrow(ax, [(930,420),(1630,420)], "Đọc danh mục / lịch sử qua Repository", (1275,446))
    arrow(ax, [(1470,225),(1550,225),(1550,355),(1630,355)], "Truy vấn / ghi", (1550,290))
    arrow(ax, [(1825,310),(1825,250)], "SQL", (1870,280))
    arrow(ax, [(1275,310),(1275,685),(1630,685)], "Đọc / cập nhật giỏ nháp", (1450,659))
    ax.text(55, 815, "CartService ghi SQL trực tiếp qua Connection; đọc sách qua BookRepository. Giỏ gửi được lưu trong transaction, giỏ nháp lưu session.",
            fontsize=10, color=MUTED)
    save(fig, "architecture")


def actor(ax, x, y, label):
    ax.add_patch(Circle((x, y - 40), 18, fill=False, edgecolor=INK, linewidth=1.5))
    for points in [((x,y-22),(x,y+30)), ((x-30,y),(x+30,y)), ((x,y+30),(x-28,y+70)), ((x,y+30),(x+28,y+70))]:
        ax.plot([p[0] for p in points], [p[1] for p in points], color=INK, linewidth=1.5)
    ax.text(x, y+105, label, ha="center", color=INK, fontsize=11, fontweight="bold")


def use_case():
    fig, ax = canvas(1900, 1400, "USE CASE · MODULE GIỎ HÀNG", "Nhóm 04 · DA23TTA  |  Phạm vi chức năng theo tác nhân")
    ax.add_patch(Rectangle((400,140),1100,1120,fill=False,edgecolor=LINE,linewidth=1.3))
    ax.text(950,175,"WEBSITE BÁN SÁCH",ha="center",color=BLUE,fontsize=12,fontweight="bold")
    actor(ax,180,260,"Khách chưa đăng nhập")
    actor(ax,180,800,"Khách hàng")
    actor(ax,1720,800,"Quản trị viên")
    groups = [
        ((215,260), 720, [(250,"Đăng ký"),(380,"Đăng nhập username")], False),
        ((215,800), 720, [(570,"Tìm và lọc sách"),(705,"Thêm / sửa lượng / bỏ sách"),(840,"Xác nhận COD / gửi nhanh"),
                          (975,"Xem lịch sử và chi tiết"),(1110,"Sửa / hủy giỏ Chờ duyệt")],False),
        ((1685,800), 1210, [(570,"Tra cứu / xem các giỏ"),(750,"Duyệt và trừ tồn kho"),(930,"Từ chối kèm lý do"),(1110,"Xóa giỏ Đã hủy / Từ chối")],True),
    ]
    for origin, center_x, cases, right in groups:
        for y, text in cases:
            edge_x = center_x + 220 if right else center_x - 220
            ax.plot([origin[0],edge_x],[origin[1],y],color="#8a9eb4",linewidth=1,zorder=1)
            ax.add_patch(Ellipse((center_x,y),440,92,facecolor="#eff5fc",edgecolor=LINE,linewidth=1.2,zorder=2))
            ax.text(center_x,y,text,ha="center",va="center",fontsize=10.5,color=INK,zorder=3)
    ax.text(55,1330,"Quyền và trạng thái được kiểm tra phía server; kiểm tra kho, giá, CSRF và nonce áp dụng theo từng thao tác.",fontsize=10,color=MUTED)
    save(fig,"use-case")


def sequence():
    fig, ax = canvas(2200,2420,"SEQUENCE · THÊM SÁCH VÀ ĐẶT HÀNG","Nhóm 04 · DA23TTA  |  Luồng thành công; các lỗi nghiệp vụ được xử lý và giữ giỏ để thử lại",
                     title_size=24, subtitle_size=13)
    lanes = [110,440,770,1100,1430,1760,2090]
    labels = ["Khách / Twig","Controller","CheckoutService","CartService","BookRepository","Session","SQL Server"]
    for x,label in zip(lanes,labels):
        ax.add_patch(Rectangle((x-105,140),210,75,facecolor="#eaf2fc",edgecolor=LINE))
        ax.text(x,178,label,ha="center",va="center",fontsize=14,color=BLUE,fontweight="bold")
        ax.plot([x,x],[215,2230],color=LINE,linestyle="--",linewidth=1)
    messages = [
        (0,1,"POST thêm sách + CSRF",False),
        (1,3,"modify(user, bookId, quantity)",False),
        (3,4,"find(bookId)",False),
        (4,6,"SELECT sách, giá, tồn kho",False),
        (6,4,"Dữ liệu sách",True),
        (4,3,"Sách hợp lệ / đủ kho",True),
        (3,5,"Lưu items và nonce mới",False),
        (1,0,"303 về giỏ hàng",True),
        (0,1,"POST checkout + CSRF + nonce",False),
        (1,2,"Kiểm tra thông tin nhận hàng",False),
        (2,1,"Ghi chú COD hợp lệ",True),
        (1,3,"submit(user, nonce, note)",False),
        (3,5,"Đọc giỏ / đối chiếu nonce",False),
        (3,6,"BEGIN; khóa và cấp mã giỏ",False),
        (3,4,"find(bookId, lock = true)",False),
        (4,6,"SELECT với HOLDLOCK",False),
        (6,4,"Giá và tồn kho hiện tại",True),
        (4,3,"Kiểm tra lại sách / giá / kho",True),
        (3,6,"INSERT GIO_HANG và MUA",False),
        (3,6,"COMMIT",False),
        (3,5,"Xóa giỏ nháp sau commit",False),
        (1,0,"303 tới chi tiết giỏ Chờ duyệt",True),
    ]
    for index,(source,target,label,dashed) in enumerate(messages):
        y=285+index*90
        ax.add_patch(FancyArrowPatch((lanes[source],y),(lanes[target],y),arrowstyle="->",mutation_scale=13,
                                    color=BLUE,linewidth=1.2,linestyle="--" if dashed else "-"))
        wrapped=textwrap.fill(f"{index+1}. {label}",width=max(22,int(abs(lanes[target]-lanes[source])/13)))
        ax.text((lanes[source]+lanes[target])/2,y-12,wrapped,ha="center",va="bottom",color=INK,fontsize=15,
                bbox={"facecolor":"white","edgecolor":"none","pad":2})
    ax.add_patch(Rectangle((55,2270),2090,115,facecolor="#f3f6fa",edgecolor=LINE))
    ax.text(85,2310,"Khi nonce / tồn kho / dữ liệu không hợp lệ hoặc lỗi ghi: rollback nếu transaction đã mở, trả 422 và giữ giỏ.",fontsize=14,color=INK)
    ax.text(85,2355,"Token CSRF sai trả 403. Thêm sách / gửi giỏ chưa trừ kho; Admin duyệt ở transaction riêng mới trừ kho.",fontsize=14,color=MUTED)
    save(fig,"cart-sequence")


if __name__ == "__main__":
    architecture()
    use_case()
    sequence()
