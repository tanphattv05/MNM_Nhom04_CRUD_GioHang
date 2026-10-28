<?php
namespace App\Controller;

use App\Service\CartService;
use App\Service\CheckoutService;
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
            $id = CartService::bookId($request->request->getString('book_id'));
            $action = $request->request->getString('action');
            $quantity = $action === 'remove' ? 1 : CartService::quantity($request->request->getString('quantity'));
            $cart->modify($this->getUser(),$id,$action,$quantity);
            $this->addFlash('success', $action === 'remove' ? 'Đã bỏ sách khỏi giỏ.' : 'Đã cập nhật giỏ hàng.');
        } catch (\DomainException $e) { $this->addFlash('cart_error',$e->getMessage()); }
        catch (\Doctrine\DBAL\Exception) { $this->addFlash('cart_error','Chưa thể cập nhật giỏ hàng. Vui lòng thử lại sau.'); }
        return $this->redirectToRoute('app_my_cart',status:Response::HTTP_SEE_OTHER);
    }

    #[Route('/gio-hang/thanh-toan', name:'app_cart_checkout', methods:['GET'])]
    public function checkout(CartService $cart): Response
    {
        $view = $cart->view($this->getUser());
        if (empty($view['lines'])) {
            $this->addFlash('cart_error', 'Giỏ hàng của bạn đang trống. Vui lòng chọn sách trước khi thanh toán.');
            return $this->redirectToRoute('app_my_cart');
        }
        if (!$view['valid']) {
            $this->addFlash('cart_error', 'Giỏ hàng có sách không hợp lệ về tồn kho hoặc giá. Vui lòng kiểm tra lại.');
            return $this->redirectToRoute('app_my_cart');
        }

        $profile = $this->getUser()->profile ?? [];
        return $this->render('gio_hang/checkout.html.twig', $view + [
            'nonce' => $cart->nonce($this->getUser()),
            'recipient_name' => $profile['THANH_VIEN_HO_TEN'] ?? '',
            'recipient_phone' => $profile['THANH_VIEN_SO_DIEN_THOAI'] ?? '',
            'recipient_address' => $profile['THANH_VIEN_DIA_CHI'] ?? '',
            'recipient_email' => $profile['THANH_VIEN_EMAIL'] ?? '',
            'note' => '',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'error' => null,
        ]);
    }

    #[Route('/gio-hang/thanh-toan', name:'app_cart_checkout_submit', methods:['POST'])]
    public function checkoutSubmit(Request $request, CartService $cart, CheckoutService $checkout): Response
    {
        if (!$this->isCsrfTokenValid('cart_checkout', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Phiên biểu mẫu không hợp lệ.');
        }

        $view = $cart->view($this->getUser());
        if (empty($view['lines'])) {
            $this->addFlash('cart_error', 'Giỏ hàng của bạn đang trống.');
            return $this->redirectToRoute('app_my_cart');
        }

        $name = trim($request->request->getString('recipient_name'));
        $phone = trim($request->request->getString('recipient_phone'));
        $address = trim($request->request->getString('recipient_address'));
        $shippingMethod = $request->request->getString('shipping_method', 'standard');
        $paymentMethod = $request->request->getString('payment_method', 'cod');
        $userNote = trim($request->request->getString('note'));
        $nonce = $request->request->getString('nonce');

        try {
            $finalNote = $checkout->note($name, $phone, $address, $shippingMethod, $paymentMethod, $userNote);
            $id = $cart->submit($this->getUser(), $nonce, $finalNote);
            $this->addFlash('success', 'Đặt hàng thành công! Đơn hàng #' . $id . ' đã được ghi nhận ở trạng thái Chờ duyệt.');
            return $this->redirectToRoute('app_cart_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) {
            $error = $e->getMessage();
        } catch (\Doctrine\DBAL\Exception) {
            $error = 'Chưa thể gửi đơn hàng. Dữ liệu chưa được lưu. Vui lòng thử lại sau.';
        }

        return $this->render('gio_hang/checkout.html.twig', $view + [
            'nonce' => $cart->nonce($this->getUser()),
            'recipient_name' => $name,
            'recipient_phone' => $phone,
            'recipient_address' => $address,
            'recipient_email' => $this->getUser()->profile['THANH_VIEN_EMAIL'] ?? '',
            'note' => $userNote,
            'shipping_method' => $shippingMethod,
            'payment_method' => $paymentMethod,
            'error' => $error,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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
