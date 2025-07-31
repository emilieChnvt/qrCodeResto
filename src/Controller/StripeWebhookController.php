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
        $sigHeader = $request->headers->get('stripe-signature');
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        // 🔐 Vérification de signature
        if (!$sigHeader) {
            $logger->warning('❌ Aucun header Stripe-Signature fourni (test uniquement)');
            $event = json_decode($payload, false);
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

        if (!isset($event->type)) {
            $logger->error('⛔️ Event type manquant');
            return new Response('Missing event type', 400);
        }

        $logger->info('📨 Événement Stripe reçu : ' . $event->type);

        switch ($event->type) {

            // 🟢 Nouveau abonnement
            case 'customer.subscription.created':
                $logger->info('📌 Subscription CREATED');
                $this->handleSubscriptionCreated($event, $userRepository, $em, $logger);
                break;

            // 🟡 Mise à jour d’un abonnement (ex: annulation à la fin de période)
            case 'customer.subscription.updated':
                $logger->info('🔄 Subscription UPDATED');
                $this->handleSubscriptionUpdated($event, $userRepository, $em, $logger);
                break;

            // 🔴 Suppression immédiate (ex: fin période ou suppression dans Stripe)
            case 'customer.subscription.deleted':
                $logger->info('🗑 Subscription DELETED');
                $this->handleSubscriptionDeleted($event, $userRepository, $em, $logger);
                break;

            default:
                $logger->info('ℹ️ Événement non géré : ' . $event->type);
                break;
        }

        return new Response('Webhook handled', 200);
    }

    private function handleSubscriptionCreated($event, $userRepository, $em, $logger)
    {
        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer ?? null;
        $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

        if (!$user) {
            $logger->warning("❌ Utilisateur non trouvé pour customerId $stripeCustomerId");
            return;
        }

        $priceId = $subscription->items->data[0]->price->id ?? null;
        if ($priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy') {
            $user->setSubscriptionPlan('pro');
            $logger->info('✅ Plan mis à jour à PRO');
        } else {
            $user->setSubscriptionPlan('free');
            $logger->info('ℹ️ Plan mis à jour à FREE');
        }

        $em->flush();
    }

    private function handleSubscriptionUpdated($event, $userRepository, $em, $logger)
    {
        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer ?? null;
        $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

        if (!$user) {
            $logger->warning("❌ Utilisateur non trouvé pour customerId $stripeCustomerId");
            return;
        }
        $logger->info('Subscription data: ' . json_encode($subscription));

        // ⏳ Stocke la date de fin d'abonnement (priorité à cancel_at si défini)
        $endsAtTimestamp = $subscription->cancel_at ?? $subscription->current_period_end ?? null;
        if ($endsAtTimestamp) {
            $user->setSubscriptionEndsAt((new \DateTimeImmutable())->setTimestamp($endsAtTimestamp));
            $logger->info('📅 Date de fin d’abonnement enregistrée : ' . date('Y-m-d H:i:s', $endsAtTimestamp));
        }

        // Vérifie si l’utilisateur a annulé son abonnement à la fin de la période
        if (!empty($subscription->cancel_at_period_end)) {
            $logger->info('📅 Abonnement annulé à la fin de la période (access encore actif)');
        }

        // Met à jour le plan en fonction du priceId
        $priceId = $subscription->items->data[0]->price->id ?? null;
        if ($priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy') {
            $user->setSubscriptionPlan('pro');
            $logger->info('✅ Plan confirmé ou mis à jour à PRO');
        } else {
            $user->setSubscriptionPlan('free');
            $logger->info('ℹ️ Plan confirmé ou mis à jour à FREE');
        }

        $em->flush();
    }


    private function handleSubscriptionDeleted($event, $userRepository, $em, $logger)
    {
        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer ?? null;
        $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

        if (!$user) {
            $logger->warning("❌ Utilisateur non trouvé pour suppression customerId $stripeCustomerId");
            return;
        }

        $user->setSubscriptionPlan('free');
        $em->flush();
        $logger->info('✅ Plan mis à jour à FREE après suppression');
    }
}
