<?php
declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class CustomExceptionListener
{
    public function __construct(private Environment $twig) {}

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: -50)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        $code = 500;
        if ($throwable instanceof HttpExceptionInterface) {
            $code = $throwable->getStatusCode();
        } elseif ($throwable instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException) {
            $code = 403;
        }

        $rawMessage = trim((string)$throwable->getMessage());

        if ($code === 404) {
            $friendly404Message = $rawMessage;
            if ($friendly404Message === '' || str_starts_with($friendly404Message, 'No route found') || str_contains($friendly404Message, 'Not Found')) {
                $friendly404Message = 'Địa chỉ đường dẫn (URL) bạn vừa truy cập không tồn tại hoặc đã bị thay đổi.';
            }

            try {
                $html = $this->twig->render('bundles/TwigBundle/Exception/error404.html.twig', [
                    'status_code' => 404,
                    'status_text' => 'Không tìm thấy trang',
                    'message' => $friendly404Message,
                ]);
                $event->setResponse(new Response($html, 404));
            } catch (\Throwable) {
                // If twig rendering fails, let default handler take over
            }
        } elseif ($code === 403) {
            $friendlyMessage = $rawMessage;
            if ($friendlyMessage === '' || $friendlyMessage === 'Access Denied.' || str_contains($friendlyMessage, 'Access Denied')) {
                $friendlyMessage = 'Bạn không có quyền truy cập vào chức năng hoặc khu vực này. Vui lòng kiểm tra lại quyền hạn tài khoản hoặc trạng thái giỏ hàng.';
            }

            try {
                $html = $this->twig->render('bundles/TwigBundle/Exception/error403.html.twig', [
                    'status_code' => 403,
                    'status_text' => 'Truy cập bị từ chối',
                    'message' => $friendlyMessage,
                ]);
                $event->setResponse(new Response($html, 403));
            } catch (\Throwable) {
                // If twig rendering fails, let default handler take over
            }
        }
    }
}
