<?php
// src/Controller/StripeWebhookController.php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class StripeWebhookController extends AbstractController
{

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST', 'GET'])]
    public function handleWebhook(
        LoggerInterface $logger,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {


        $payload = $request->getContent();
        $event = json_decode($payload, false); // false = objet, plus pratique ici
        $logger->info('Received Stripe event: ' . json_encode($event));



        $sigHeader = $request->headers->get('stripe-signature');
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'];

// Si le header de signature est absent, on bypass la validation (mode test uniquement)
        if (!$sigHeader) {
            $logger->warning('❌ Aucun header Stripe-Signature fourni, bypass validation pour test');
            $event = json_decode($payload); // On décode directement sans validation
        } else {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
                $logger->info('✅ Signature Stripe vérifiée');
            } catch (\UnexpectedValueException $e) {
                $logger->error('❌ JSON invalide');
                return new Response('Invalid payload', 400);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                $logger->error('❌ Signature Stripe invalide');
                return new Response('Invalid signature', 400);
            }
        }

        if (!$event) {
            $logger->error('Event is null or invalid JSON');
            return new Response('Invalid event', 400);
        }

        if (!isset($event->type)) {
            $logger->error('Event type is missing');
            return new Response('Missing event type', 400);
        }



        switch ($event->type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $subscription = $event->data->object;

                // Récupération du priceId
                if (isset($subscription->plan) && isset($subscription->plan->id)) {
                    $priceId = $subscription->plan->id;
                } elseif (isset($subscription->items->data[0]->price->id)) {
                    $priceId = $subscription->items->data[0]->price->id;
                } else {
                    $priceId = null;
                }

            $logger->info('Traitement webhook subscription');

            $subscription = $event->data->object;

            $priceId = null;

            if (isset($subscription->plan) && isset($subscription->plan->id)) {
                $priceId = $subscription->plan->id;
            } elseif (isset($subscription->items) && isset($subscription->items->data) && count($subscription->items->data) > 0) {
                $item = $subscription->items->data[0];
                if (isset($item->price) && isset($item->price->id)) {
                    $priceId = $item->price->id;
                } elseif (isset($item->plan) && isset($item->plan->id)) {
                    $priceId = $item->plan->id;
                }
            }

            $logger->info('Price ID détecté : ' . $priceId);

            $stripeCustomerId = $subscription->customer;

            $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

            if ($user) {
                $logger->info('Utilisateur trouvé : ' . $user->getEmail());
                if ($priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy') {
                    $user->setSubscriptionPlan('pro');
                    $logger->info('Plan mis à jour à PRO');
                } else {
                    $user->setSubscriptionPlan('free');
                    $logger->info('Plan mis à jour à FREE');
                }
                $em->flush();
            } else {
                $logger->warning('Aucun utilisateur trouvé pour customerId: ' . $stripeCustomerId);
            }}

        return new Response('Webhook handled', 200);
    }


    #[Route('/test-log', name: 'test_log')]
    public function testLog(LoggerInterface $logger)
    {
        $logger->info('Test log simple');
        return new Response('Check logs');
    }

}
