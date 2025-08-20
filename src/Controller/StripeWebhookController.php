<?php
// src/Controller/StripeWebhookController.php

namespace App\Controller;

use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
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
            if ($this->getParameter('kernel.environment') === 'prod') {
                // ✅ En production : vérification obligatoire de la signature
                $event = Webhook::constructEvent(
                    $payload,
                    $sigHeader,
                    $this->stripeWebhookSecret
                );
                $logger->info('✅ Signature Stripe vérifiée');
            } else {
                // ⚠️ En développement : on ignore la signature
                // ⚠️ En développement : on ignore la signature mais on crée un objet Stripe Event
                $event = \Stripe\Event::constructFrom(json_decode($payload, true));
                $logger->info('⚠️ Signature Stripe ignorée en environnement local, objet Event créé');

            }
        } catch (SignatureVerificationException $e) {
            $logger->error('❌ Signature Stripe invalide : ' . $e->getMessage());
            return new Response('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            $logger->error('❌ JSON invalide : ' . $e->getMessage());
            return new Response('Invalid payload', 400);
        }

        // 🔄 Transmission de l’event à ton service
        $handled = $this->subscriptionService->handleStripeEvent($event);

        if ($handled) {
            $logger->info("✅ Webhook Stripe traité avec succès pour l'événement : " . $event->type
            );
            return new Response('Webhook handled', 200);
        }
        $logger->info('Payload reçu : ' . $payload);
        $logger->info('Type d’événement : ' . $event->type);

        $logger->warning("⚠️ Webhook Stripe non pris en charge pour l'événement : " . $event->type
        );
        // Toujours retourner 200 pour que Stripe n’essaie pas de renvoyer l’événement en boucle
        return new Response('Event not handled but acknowledged', 200);
    }
}
