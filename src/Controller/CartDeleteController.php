<?php
namespace App\Controller;

use App\Repository\CartRepository;
use App\Service\CartService;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CartDeleteController extends AbstractController
{
    #[Route('/gio-hang/lich-su/{id}/huy', name: 'app_cart_cancel', requirements: ['id'=>'\d{1,10}'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $id, Request $request, CartRepository $carts, CartService $service): Response
    {
        if ($this->getUser()->admin) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('cart_cancel_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.');
        }
        if (!$carts->detail($id, $this->getUser()->id)) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
        try {
            $service->cancel($this->getUser(), $id);
            $this->addFlash('success', 'Đã hủy giỏ hàng #'.$id.'. Lịch sử giỏ hàng được giữ lại.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            $this->addFlash('error', 'Chưa thể hủy giỏ hàng. Các thay đổi đã được hoàn tác. Vui lòng thử lại.');
        }
        return $this->redirectToRoute('app_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/gio-hang/{id}/xoa', name: 'app_admin_cart_delete', requirements: ['id'=>'\d{1,10}'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, Request $request, CartRepository $carts, CartService $service): Response
    {
        if (!$this->isCsrfTokenValid('cart_delete_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.');
        }
        if (!$carts->detail($id, null)) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
        try {
            $service->delete($this->getUser(), $id);
            $this->addFlash('success', 'Đã xóa giỏ hàng #'.$id.' và các dòng sách liên quan.');
            return $this->redirectToRoute('app_admin_carts', [], Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            $this->addFlash('error', 'Chưa thể xóa giỏ hàng do ràng buộc dữ liệu hoặc lỗi cơ sở dữ liệu. Các thay đổi đã được hoàn tác.');
        }
        return $this->redirectToRoute('app_admin_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
    }
}
