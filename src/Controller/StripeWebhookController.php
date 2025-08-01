<?php
// src/Controller/StripeWebhookController.php

namespace App\Controller;

use App\Service\MailgunService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class StripeWebhookController extends AbstractController
{
    private string $stripeWebhookSecret;

    public function __construct(string $stripeWebhookSecret)
    {
        $this->stripeWebhookSecret = $stripeWebhookSecret;
    }
    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(
        LoggerInterface $logger,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailgunService $mailgunService
    ): Response {


        $payload = $request->getContent(); // PAS de json_decode ici avant vérif
        $logger->info('📥 Payload brut reçu : ' . $payload);

        $sigHeader = $request->headers->get('stripe-signature');
        $logger->info('🔑 Header signature Stripe : ' . $sigHeader);

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
            $logger->info('✅ Signature Stripe vérifiée');
        } catch (\UnexpectedValueException $e) {
            $logger->error('❌ JSON invalide : ' . $e->getMessage());
            return new Response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            $logger->error('❌ Signature Stripe invalide : ' . $e->getMessage());
            $logger->error('Payload length: ' . strlen($payload));
            $logger->error('Signature header: ' . $sigHeader);
            return new Response('Invalid signature', 400);
        }



        if (!isset($event->type)) {
            $logger->error('⛔️ Event type manquant');
            return new Response('Missing event type', 400);
        }

        $logger->info('Event reçu : ' . json_encode($event));

        $logger->info('📨 Événement Stripe reçu : ' . $event->type);

        switch ($event->type) {
            case 'customer.subscription.created':
                $logger->info('📌 Appel handleSubscriptionCreated avec event : ' . json_encode($event));
                $this->handleSubscriptionCreated($event, $userRepository, $em, $logger, $mailgunService);
                break;

            case 'customer.subscription.updated':
                $logger->info('🔄 Appel handleSubscriptionUpdated avec event : ' . json_encode($event));
                $this->handleSubscriptionUpdated($event, $userRepository, $em, $logger, $mailgunService);
                break;

            case 'customer.subscription.deleted':
                $logger->info('🗑 Appel handleSubscriptionDeleted avec event : ' . json_encode($event));
                $this->handleSubscriptionDeleted($event, $userRepository, $em, $logger, $mailgunService);
                break;

            default:
                $logger->info('ℹ️ Événement non géré : ' . $event->type);
                break;
        }

        return new Response('Webhook handled', 200);
    }



    private function handleSubscriptionCreated($event, $userRepository, $em, $logger, $mailgunService)
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

    private function handleSubscriptionUpdated($event, $userRepository, $em, $logger, $mailgunService)
    {
        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer ?? null;
        $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

        if (!$user) {
            $logger->warning("❌ Utilisateur non trouvé pour customerId $stripeCustomerId");
            return;
        }

        // Récupère la date de fin d'abonnement si elle existe
        $endsAtTimestamp = $subscription->cancel_at ?? $subscription->current_period_end ?? null;
        if ($endsAtTimestamp) {
            $user->setSubscriptionEndsAt((new \DateTimeImmutable())->setTimestamp($endsAtTimestamp));
            $logger->info('📅 Date de fin d’abonnement enregistrée : ' . date('Y-m-d H:i:s', $endsAtTimestamp));
        }
        if (!empty($subscription->cancel_at_period_end)) {
            $logger->info('📅 Abonnement annulé à la fin de la période (access encore actif)');
        }


        // Mise à jour du plan en fonction du price ID
        $priceId = $subscription->items->data[0]->price->id ?? null;
        if ($priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy') {
            $user->setSubscriptionPlan('pro');
            $logger->info('✅ Plan mis à jour à PRO');
        } else {
            $user->setSubscriptionPlan('free');
            $logger->info('ℹ️ Plan mis à jour à FREE');
        }

        $em->flush();
        $logger->info('🔍 Date de fin en base : ' . ($user->getSubscriptionEndsAt() ? $user->getSubscriptionEndsAt()->format('d/m/Y H:i:s') : 'aucune'));


        // Envoi email si annulation prévue à la fin de la période
        if (!empty($subscription->cancel_at_period_end) && $endsAtTimestamp) {
            $endsAtFormatted = date('d/m/Y', $endsAtTimestamp);

            $mailgunService->send(
                $user->getEmail(),
                'Votre abonnement a été annulé',
                "Bonjour {$user->getEmail()},\n\nVotre abonnement a bien été annulé. Vous aurez toujours accès à votre menu QR Code jusqu'au $endsAtFormatted.\n\nMerci pour votre confiance !"
            );

            $logger->info('📧 Email d’annulation envoyé à ' . $user->getEmail());
        }
    }




    private function handleSubscriptionDeleted($event, $userRepository, $em, $logger, $mailgunService)
    {
        $logger->info('🔔 Entrée dans handleSubscriptionDeleted');

        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer ?? null;
        $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

        if (!$user) {
            $logger->warning("❌ Utilisateur non trouvé pour suppression customerId $stripeCustomerId");
            return;
        }

        $user->setSubscriptionPlan('free');
        $user->setSubscriptionEndsAt(null);


        $logger->info('✅ Plan mis à jour à FREE après suppression');
        $logger->info('📦 Données subscription supprimée : ' . json_encode($subscription));

        $em->flush();

        $mailgunService->send(
            $user->getEmail(),
            'Votre abonnement a été annulé',
            "Bonjour {$user->getEmail()},\n\nVotre abonnement a pris fin aujopurdhui. Vous n'avez plus accès à la modification de votre menu QR Code .\n\nMerci pour votre confiance !"
        );

    }
}
