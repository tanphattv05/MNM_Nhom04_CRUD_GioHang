<?php
namespace App\Security;

use App\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\{Request, Response, RedirectResponse};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\{UserBadge, CsrfTokenBadge};
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

final class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    public function __construct(private AccountRepository $accounts, private UrlGeneratorInterface $urls) {}
    public function supports(Request $request): bool { return $request->isMethod('POST') && in_array($request->attributes->get('_route'), ['app_login','app_admin_login'], true); }
    public function authenticate(Request $request): Passport
    {
        $admin = $request->attributes->get('_route') === 'app_admin_login';
        $login = substr(trim($request->request->getString('_username')), 0, 150);
        return new Passport(new UserBadge(($admin ? 'admin:' : 'user:').$login, function () use ($login, $admin) {
            $user = $this->accounts->byLogin($login, $admin);
            // Legacy plaintext is never accepted by authentication.
            if (!$user || !password_get_info($user->getPassword())['algo']) { throw new UserNotFoundException(); }
            return $user;
        }), new PasswordCredentials($request->request->getString('_password')), [new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token'))]);
    }
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urls->generate(in_array('ROLE_ADMIN', $token->getRoleNames(), true) ? 'app_admin_dashboard' : 'app_customer_home'));
    }
    protected function getLoginUrl(Request $request): string { return $this->urls->generate(str_starts_with($request->getPathInfo(), '/admin') ? 'app_admin_login' : 'app_login'); }
}
