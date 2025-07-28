<?php

namespace App\Controller;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{

    private $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    #[Route('/payment', name: 'payment_index')]
    public function index(): Response
    {
        $priceLookupKey = 'cle';

        return $this->render('payment/index.html.twig', [
            'PRICE_LOOKUP_KEY' => $priceLookupKey,
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'], // ou depuis config/services.yaml
        ]);
    }


    #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Request $request, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $lookupKey = $request->request->get('lookup_key', 'cli');

        // Génére les URLs avec generateUrl et option absolute_url = true
        $successUrl = $urlGenerator->generate('payment_success', [
            'session_id' => '{CHECKOUT_SESSION_ID}'
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $cancelUrl = $urlGenerator->generate('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $checkoutSession = $this->stripeService->createCheckoutSession(
            $lookupKey,
            $successUrl,
            $cancelUrl
        );
        if (!isset($checkoutSession->url)) {
            throw new \Exception('La session Stripe a échoué : aucune URL retournée.');
        }

        return new JsonResponse(['id' => $checkoutSession->id]);
    }

    #[Route('/payment/success', name: 'payment_success')]
    public function success()
    {
        return $this->render('payment/success.html.twig');
    }

    #[Route('/payment/cancel', name: 'payment_cancel')]
    public function cancel()
    {
        return $this->render('payment/cancel.html.twig');
    }
}
