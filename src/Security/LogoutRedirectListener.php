<?php
namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutRedirectListener
{
    public function __construct(private UrlGeneratorInterface $urls) {}

    #[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.main', priority: -64)]
    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(new RedirectResponse($this->urls->generate('app_login'), 303));
    }
}
