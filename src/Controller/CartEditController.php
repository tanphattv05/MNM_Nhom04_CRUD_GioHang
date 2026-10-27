<?php
namespace App\Controller;

use App\Repository\{BookRepository, CartRepository};
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CartEditController extends AbstractController
{
    #[Route('/gio-hang/lich-su/{id}/sua', name: 'app_cart_edit', requirements: ['id'=>'\d{1,10}'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, CartRepository $carts, BookRepository $books, CartService $service): Response
    {
        if ($this->getUser()->admin) {
            throw $this->createAccessDeniedException('Chỉ khách hàng được sửa giỏ của mình.');
        }
        $data = $carts->detail($id, $this->getUser()->id);
        if (!$data) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
        if ($data['cart']['GIO_HANG_TRANG_THAI'] !== 'Chờ duyệt') {
            if ($request->isMethod('POST')) {
                $this->addFlash('error', 'Giỏ hàng #'.$id.' đã được Admin xử lý (không còn ở trạng thái Chờ duyệt). Không thể lưu thay đổi.');
                return $this->redirectToRoute('app_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
            }
            throw $this->createAccessDeniedException('Chỉ được sửa giỏ hàng Chờ duyệt.');
        }
        $items = [];
        foreach ($data['lines'] as $line) { $items[$line['SACH_ID_SACH']] = (string)$line['MUA_SO_LUONG_MUA']; }
        $note = $data['cart']['GIO_HANG_GHI_CHU'] ?? '';
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cart_edit_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.');
            }
            $items = $request->request->all('items');
            $note = trim($request->request->getString('note'));
            try {
                // A checked remove control omits the line, including invalid quantities.
                foreach ($request->request->all('remove') as $bookId => $remove) { unset($items[$bookId]); }
                $newId = $request->request->getString('new_book');
                if ($newId !== '') {
                    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $newId) || (int)$newId > 2147483647) {
                        throw new \DomainException('Mã sách không hợp lệ.');
                    }
                    $newId = (int)$newId;
                    $quantity = CartService::quantity($request->request->getString('new_quantity'));
                    if (isset($items[$newId])) {
                        if (!is_string($items[$newId])) { throw new \DomainException('Số lượng không hợp lệ.'); }
                        $quantity += CartService::quantity($items[$newId]);
                    }
                    $items[$newId] = (string)$quantity;
                }
                $service->updateSubmitted($this->getUser(), $id, $items, $note);
                $this->addFlash('success', 'Đã cập nhật giỏ hàng #'.$id.'.');
                return $this->redirectToRoute('app_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                if (str_contains($e->getMessage(), 'Chờ duyệt')) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('app_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
                }
                $error = $e->getMessage();
            }
            catch (\Doctrine\DBAL\Exception) { $error = 'Chưa thể cập nhật giỏ hàng. Dữ liệu chưa được lưu. Vui lòng thử lại.'; }
        }
        $lines = [];
        foreach ($items as $bookId => $quantity) {
            $lines[] = ['id'=>$bookId, 'book'=>ctype_digit((string)$bookId) ? $books->find((int)$bookId) : false, 'quantity'=>is_string($quantity) ? $quantity : ''];
        }
        $q = mb_substr(trim($request->query->getString('q')), 0, 120);
        $catalog = $books->catalog(['q'=>$q, 'category'=>'', 'min'=>'', 'max'=>'', 'sort'=>'name'], $request->query->getInt('page', 1));
        return $this->render('gio_hang/edit.html.twig', $catalog + ['cart'=>$data['cart'], 'lines'=>$lines, 'note'=>$note, 'error'=>$error, 'q'=>$q], new Response(status: $error ? 422 : 200));
    }
}
