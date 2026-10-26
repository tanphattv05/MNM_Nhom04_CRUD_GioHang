<?php
namespace App\Controller;

use App\Repository\CartRepository;
use App\Service\CartService;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CartReviewController extends AbstractController
{
    #[Route('/admin/gio-hang/{id}/{action}', name: 'app_admin_cart_review', requirements: ['id'=>'\d{1,10}', 'action'=>'approve|reject'], methods: ['POST'])]
    public function review(int $id, string $action, Request $request, CartRepository $carts, CartService $service): Response
    {
        if (!$this->isCsrfTokenValid('cart_'.$action.'_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.');
        }
        if (!$carts->detail($id, null)) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
        try {
            $service->review($this->getUser(), $id, $action, $request->request->getString('reason'));
            $this->addFlash('success', $action === 'approve' ? 'Đã duyệt giỏ hàng và trừ tồn kho.' : 'Đã từ chối giỏ hàng.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (Exception) {
            $this->addFlash('error', 'Chưa thể xử lý giỏ hàng. Các thay đổi đã được hoàn tác. Vui lòng thử lại.');
        }
        return $this->redirectToRoute('app_admin_cart_detail', ['id'=>$id], Response::HTTP_SEE_OTHER);
    }
}
