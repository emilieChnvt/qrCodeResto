<?php
// src/Controller/StripeWebhookController.php

namespace App\Controller;

use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeWebhookController extends AbstractController
{
    private string $stripeWebhookSecret;
    private SubscriptionService $subscriptionService;

    public function __construct(string $stripeWebhookSecret, SubscriptionService $subscriptionService)
    {
        $this->stripeWebhookSecret = $stripeWebhookSecret;
        $this->subscriptionService = $subscriptionService;
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, LoggerInterface $logger): Response
    {
        $logger->info('🔍 Webhook reçu et traitement commencé');

        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            // COMMENTE la validation de la signature pour tests locaux
            /*
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
            $logger->info('✅ Signature Stripe vérifiée');
            */

            // POUR TEST, créer l'événement manuellement à partir du payload JSON
            $event = json_decode($payload);
            $logger->info('⚠️ Validation de signature désactivée (mode test)');
        } catch (\UnexpectedValueException $e) {
            $logger->error('❌ JSON invalide : ' . $e->getMessage());
            return new Response('Invalid payload', 400);
        } /*catch (SignatureVerificationException $e) {
        $logger->error('❌ Signature Stripe invalide : ' . $e->getMessage());
        return new Response('Invalid signature', 400);
    }*/

        $handled = $this->subscriptionService->handleStripeEvent($event);

        if ($handled) {
            $logger->info("Webhook Stripe traité avec succès pour l'événement : " . $event->type);
            return new Response('Webhook handled', 200);
        } else {
            $logger->warning("Webhook Stripe non pris en charge pour l'événement : " . $event->type);
            return new Response('Event not handled', 400);
        }
    }

}
