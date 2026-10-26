<?php
namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class GioHangController extends AbstractController
{
    #[Route('/gio-hang', name:'app_my_cart', methods:['GET'])]
    public function index(Request $request, CartService $cart): Response
    {
        return $this->renderCart($cart);
    }

    #[Route('/gio-hang/new', name:'app_gio_hang_new', methods:['GET'])]
    public function new(): Response { return $this->redirectToRoute('app_my_cart'); }

    #[Route('/gio-hang/thay-doi', name:'app_cart_modify', methods:['POST'])]
    public function modify(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_modify', $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.'); }
        try {
            $id = CartService::quantity($request->request->getString('book_id'));
            $action = $request->request->getString('action');
            $quantity = $action === 'remove' ? 1 : CartService::quantity($request->request->getString('quantity'));
            $cart->modify($this->getUser(),$id,$action,$quantity);
            $this->addFlash('success', $action === 'remove' ? 'Đã bỏ sách khỏi giỏ.' : 'Đã cập nhật giỏ hàng.');
        } catch (\DomainException $e) { $this->addFlash('cart_error',$e->getMessage()); }
        catch (\Doctrine\DBAL\Exception) { $this->addFlash('cart_error','Chưa thể cập nhật giỏ hàng. Vui lòng thử lại sau.'); }
        return $this->redirectToRoute('app_my_cart',status:Response::HTTP_SEE_OTHER);
    }

    #[Route('/gio-hang/gui', name:'app_cart_submit', methods:['POST'])]
    public function submit(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_submit', $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.'); }
        $note = trim($request->request->getString('note'));
        try {
            $id = $cart->submit($this->getUser(),$request->request->getString('nonce'),$note);
            $this->addFlash('success','Đã gửi giỏ hàng #'.$id.'. Trạng thái: Chờ duyệt.');
            return $this->redirectToRoute('app_my_cart',status:Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) { $error = $e->getMessage(); }
        catch (\Doctrine\DBAL\Exception) { $error = 'Chưa thể gửi giỏ hàng. Dữ liệu chưa được lưu. Vui lòng thử lại sau.'; }
        return $this->renderCart($cart,$note,$error,Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function renderCart(CartService $cart, string $note = '', ?string $error = null, int $status = 200): Response
    {
        return $this->render('gio_hang/index.html.twig',$cart->view($this->getUser()) + ['nonce'=>$cart->nonce($this->getUser()),'note'=>$note,'error'=>$error],new Response(status:$status));
    }
}