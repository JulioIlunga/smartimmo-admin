<?php

namespace App\Security;


use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class UsersAuthenticator extends AbstractLoginFormAuthenticator
{

    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    private LoggerInterface $logger;



    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, RouterInterface $router, LoggerInterface $logger)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->logger = $logger;

    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('username', '');

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

//    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
//    {
//        $target = $request->get('_target_path') ?? $this->urlGenerator->generate('app_account');
//
//        if ($request->isXmlHttpRequest()) {
//            return new JsonResponse([
//                'success' => true,
//                'redirect' => $target
//            ]);
//        }
//
//        return new RedirectResponse($target);
//    }
//    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
//    {
//        if ($request->isXmlHttpRequest()) {
//            return new JsonResponse([
//                'success' => false,
//                'error' => 'Identifiants incorrects.'
//            ], 401);
//        }
//
//        // fallback to login page
//        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
//        return new RedirectResponse($this->router->generate('app_login'));
//    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('LOGSEEMSTOBEHERE');
        $this->logger->info('Login attempt detected', [
            'isAjax' => $request->isXmlHttpRequest(),
            'request_uri' => $request->getRequestUri(),
        ]);

        if ($request->isXmlHttpRequest()) {
            $this->logger->info('Detected AJAX login request.');
            return new JsonResponse(['success' => true, 'redirect' => '/']);
        }

        $this->logger->info('Non-AJAX login request, redirecting.');
        return new RedirectResponse('/');

    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Identifiants invalides' // You could also use $exception->getMessageKey()
            ], 401);
        }

        return new RedirectResponse($this->getLoginUrl($request));
    }


    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
