<?php
namespace App\Controller;

use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CartReadController extends AbstractController
{
    #[Route('/gio-hang/lich-su', name: 'app_cart_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function history(Request $request, CartRepository $carts): Response
    {
        return $this->listing($request, $carts, false);
    }

    #[Route('/admin/gio-hang', name: 'app_admin_carts', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request, CartRepository $carts): Response
    {
        return $this->listing($request, $carts, true);
    }

    private function listing(Request $request, CartRepository $carts, bool $admin): Response
    {
        $owner = $admin ? null : $this->getUser()->id;
        $status = mb_substr(trim($request->query->getString('status')), 0, 30);
        $q = $admin ? mb_substr(trim($request->query->getString('q')), 0, 120) : '';
        return $this->render('gio_hang/history.html.twig', $carts->search($owner, $status, $q, $request->query->getInt('page', 1)) + [
            'admin'=>$admin, 'status'=>$status, 'q'=>$q, 'statuses'=>$carts->statuses($owner),
        ]);
    }

    #[Route('/gio-hang/lich-su/{id}', name: 'app_cart_detail', requirements: ['id'=>'\d{1,10}'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function detail(int $id, CartRepository $carts): Response
    {
        return $this->show($id, $carts, false);
    }

    #[Route('/admin/gio-hang/{id}', name: 'app_admin_cart_detail', requirements: ['id'=>'\d{1,10}'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDetail(int $id, CartRepository $carts): Response
    {
        return $this->show($id, $carts, true);
    }

    private function show(int $id, CartRepository $carts, bool $admin): Response
    {
        $data = $carts->detail($id, $admin ? null : $this->getUser()->id);
        if (!$data) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
        return $this->render('gio_hang/detail.html.twig', $data + ['admin'=>$admin]);
    }
}
