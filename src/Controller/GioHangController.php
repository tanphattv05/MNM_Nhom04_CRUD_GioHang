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
            return $this->redirectToRoute('app_my_cart', status: Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) { $this->addFlash('cart_error',$e->getMessage()); }
        catch (\Doctrine\DBAL\Exception) { $this->addFlash('cart_error','Chưa thể cập nhật giỏ hàng. Vui lòng thử lại sau.'); }
        // Only accept a local catalog URL supplied by the add forms, never an external redirect.
        $returnTo = $request->request->getString('return_to');
        $catalog = $this->generateUrl('app_catalog');
        if ($request->request->getString('action') === 'add'
            && ($returnTo === $catalog || str_starts_with($returnTo, $catalog.'?') || str_starts_with($returnTo, $catalog.'#'))
            && !preg_match('/[\x00-\x20\x7f\\\\]/', $returnTo)) {
            return $this->redirect($returnTo, Response::HTTP_SEE_OTHER);
        }
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
            $this->addFlash('cart_error', 'Một số sách trong giỏ vượt quá tồn kho. Vui lòng điều chỉnh lại số lượng.');
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
    public function checkoutSubmit(Request $request, CartService $cart): Response
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

        $error = null;
        if ($name === '') {
            $error = 'Vui lòng nhập họ và tên người nhận hàng.';
        } elseif (mb_strlen($name) > 100) {
            $error = 'Họ và tên tối đa 100 ký tự.';
        } elseif ($phone === '') {
            $error = 'Vui lòng nhập số điện thoại nhận hàng.';
        } elseif (!preg_match('/^[0-9+\s().-]{9,20}$/', $phone)) {
            $error = 'Số điện thoại không hợp lệ (từ 9 đến 20 chữ số).';
        } elseif ($address === '') {
            $error = 'Vui lòng nhập địa chỉ nhận hàng chi tiết.';
        } elseif (mb_strlen($address) > 200) {
            $error = 'Địa chỉ giao hàng tối đa 200 ký tự.';
        }

        if ($error === null) {
            $shippingLabel = $shippingMethod === 'express' ? 'Hỏa tốc' : 'Tiêu chuẩn';
            $paymentLabel = match ($paymentMethod) {
                'banking' => 'Chuyển khoản QR',
                'vnpay' => 'VNPAY',
                default => 'COD (Tiền mặt)',
            };

            $noteParts = [];
            $noteParts[] = "Người nhận: {$name} - {$phone}";
            $noteParts[] = "Đ/C: {$address}";
            $noteParts[] = "PTTT: {$paymentLabel} | VC: {$shippingLabel}";
            if ($userNote !== '') {
                $noteParts[] = "Ghi chú: {$userNote}";
            }
            $finalNote = mb_substr(implode(' | ', $noteParts), 0, 255);

            try {
                $id = $cart->submit($this->getUser(), $nonce, $finalNote);
                $this->addFlash('success', 'Đặt hàng thành công! Đơn hàng #' . $id . ' đã được ghi nhận ở trạng thái Chờ duyệt.');
                return $this->redirectToRoute('app_cart_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $error = $e->getMessage();
            } catch (\Doctrine\DBAL\Exception) {
                $error = 'Chưa thể gửi đơn hàng. Dữ liệu chưa được lưu. Vui lòng thử lại sau.';
            }
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
