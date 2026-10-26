<?php
namespace App\Controller;
use App\Form\{AccountType, PasswordType};
use App\Security\Account;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
final class AccountController extends AbstractController
{
    #[Route('/dang-nhap', name:'app_login', methods:['GET','POST'])]
    #[Route('/admin/dang-nhap', name:'app_admin_login', methods:['GET','POST'])]
    public function login(Request $request, AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) { return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_dashboard' : 'app_customer_home'); }
        return $this->render('account/login.html.twig',['admin'=>$request->attributes->get('_route') === 'app_admin_login','error'=>$auth->getLastAuthenticationError()]);
    }
    #[Route('/dang-xuat', name:'app_logout', methods:['POST'])]
    public function logout(): never { throw new \LogicException('Handled by Security.'); }
    #[Route('/dang-ky', name:'app_register', methods:['GET','POST'])]
    public function register(Request $request, AccountRepository $accounts, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) { return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_dashboard' : 'app_customer_home'); }
        $form = $this->createForm(AccountType::class, null, ['register'=>true]); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData(); $data['email'] = strtolower(trim($data['email']));
            try {
                $accounts->register($data, $hasher->hashPassword(new Account('',false,''), $data['password']));
                $this->addFlash('success','Đăng ký thành công. Mời bạn đăng nhập.'); return $this->redirectToRoute('app_login');
            } catch (\DomainException) { $form->get('email')->addError(new FormError('Email đã được sử dụng.')); }
            catch (\Doctrine\DBAL\Exception) { $form->addError(new FormError('Chưa thể lưu tài khoản. Vui lòng thử lại sau.')); }
        }
        return $this->render('account/form.html.twig',['form'=>$form,'title'=>'Đăng ký tài khoản','button'=>'Đăng ký']);
    }
    #[Route('/tai-khoan', name:'app_profile', methods:['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function profile(Request $request, AccountRepository $accounts): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(AccountType::class, ['name'=>$user->profile['THANH_VIEN_HO_TEN'], 'phone'=>$user->profile['THANH_VIEN_SO_DIEN_THOAI'], 'address'=>$user->profile['THANH_VIEN_DIA_CHI']]); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try { $accounts->updateProfile($user,$form->getData()); $this->addFlash('success','Đã cập nhật hồ sơ.'); return $this->redirectToRoute('app_profile'); }
            catch (\Doctrine\DBAL\Exception) { $form->addError(new FormError('Chưa thể cập nhật. Vui lòng thử lại sau.')); }
        }
        return $this->render('account/form.html.twig',['form'=>$form,'title'=>'Hồ sơ cá nhân','button'=>'Lưu thông tin','email'=>$user->profile['THANH_VIEN_EMAIL']]);
    }
    #[Route('/tai-khoan/doi-mat-khau', name:'app_password', methods:['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function password(Request $request, AccountRepository $accounts, UserPasswordHasherInterface $hasher, TokenStorageInterface $tokens): Response
    {
        $form = $this->createForm(PasswordType::class); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $accounts->changePassword($this->getUser(),$hasher->hashPassword($this->getUser(),$form->getData()['password']));
                $tokens->setToken(null); $request->getSession()->invalidate(); return $this->redirectToRoute('app_login');
            } catch (\Doctrine\DBAL\Exception) { $form->addError(new FormError('Chưa thể đổi mật khẩu. Vui lòng thử lại sau.')); }
        }
        return $this->render('account/form.html.twig',['form'=>$form,'title'=>'Đổi mật khẩu','button'=>'Đổi mật khẩu và đăng nhập lại']);
    }
    #[Route('/trang-chu', name:'app_customer_home', methods:['GET'])]
    #[IsGranted('ROLE_USER')]
    public function home(): Response { return $this->render('account/dashboard.html.twig',['admin'=>false,'section'=>'Chào mừng bạn đến Nhà sách Nhóm 04']); }
    #[Route('/admin/dashboard', name:'app_admin_dashboard', methods:['GET'])]
    #[Route('/admin/gio-hang', name:'app_admin_carts', methods:['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response { return $this->render('account/dashboard.html.twig',['admin'=>true,'section'=>'Dashboard quản trị']); }
}
