<?php

// src/Service/SubscriptionService.php

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionService
{
    public function __construct(
        private LoggerInterface        $logger,
        private UserRepository         $userRepository,
        private EntityManagerInterface $em,
        private MailgunService         $mailgunService,
        private StripeClient           $stripeClient
    )
    {
    }

    public function handleStripeEvent($event): bool
    {
        return match ($event->type) {
            'customer.subscription.created' => $this->handleCreated($event),
            'customer.subscription.updated' => $this->handleUpdated($event),
            'customer.subscription.deleted' => $this->handleDeleted($event),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event), // <== ajout


            default => false,
        };
    }

    private function handleCreated($event): bool
    {
        $subscription = $event->data->object;
        $customerId = $subscription->customer ?? null;

        $this->logger->info("🟢 handleCreated appelé pour customerId : $customerId");

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if (!$user) {
            $this->logger->warning("❌ Utilisateur non trouvé pour customerId $customerId");
            return false;
        }
        $this->logger->info("✅ Utilisateur trouvé : id " . $user->getId());

        $user->setSubscriptionPlan('pro');
        $user->setIsSubscriptionCanceled(false);

        $this->logger->info("📦 Plan d'abonnement mis à jour à : $plan");

        $currentPeriodEnd = $subscription->current_period_end ?? null;
        $this->logger->info('⏳ current_period_end reçu : ' . var_export($currentPeriodEnd, true));

        $currentPeriodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $endedAt = $subscription->ended_at ?? null;

        $endsAt = null;
        if ($currentPeriodEnd) {
            $endsAt = (new \DateTimeImmutable())
                ->setTimestamp($currentPeriodEnd)
                ->setTimezone(new \DateTimeZone('Europe/Paris'));
        } elseif ($endedAt) {
            $endsAt = (new \DateTimeImmutable())
                ->setTimestamp($endedAt)
                ->setTimezone(new \DateTimeZone('Europe/Paris'));
        }

        if ($endsAt) {
            $user->setSubscriptionEndsAt($endsAt);
            $this->logger->info("📅 Date de fin de période mise à jour : " . $endsAt->format('Y-m-d H:i:s'));
        } else {
            $this->logger->warning("⚠️ Aucune date de fin trouvée dans l'événement");
        }


        $this->em->flush();
        $this->logger->info("💾 Base de données mise à jour avec le nouvel abonnement.");

        return true;
    }

    private function handleUpdated($event): bool
    {
        $subscription = $event->data->object;
        $customerId = $subscription->customer ?? null;

        $this->logger->info("🟠 handleUpdated appelé pour customerId : $customerId");

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if (!$user) {
            $this->logger->warning("❌ Utilisateur non trouvé pour customerId $customerId");
            return false;
        }
        $this->logger->info("✅ Utilisateur trouvé : id " . $user->getId());

        $cancelAtPeriodEnd = $subscription->cancel_at_period_end ?? false;

        if ($cancelAtPeriodEnd) {
            $user->setIsSubscriptionCanceled(true);
        } else {
            $user->setIsSubscriptionCanceled(false);
        }

        $currentPeriodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $endedAt = $subscription->ended_at ?? null;

        if ($currentPeriodEnd) {
            $endsAt = (new \DateTimeImmutable())
                ->setTimestamp($currentPeriodEnd)
                ->setTimezone(new \DateTimeZone('Europe/Paris'));
        } elseif ($endedAt) {
            $endsAt = (new \DateTimeImmutable())
                ->setTimestamp($endedAt)
                ->setTimezone(new \DateTimeZone('Europe/Paris'));
        } else {
            $endsAt = null;
        }

        if ($endsAt) {
            $user->setSubscriptionEndsAt($endsAt);
            $this->logger->info("📅 Date de fin de période mise à jour : " . $endsAt->format('Y-m-d H:i:s'));
        } else {
            $this->logger->warning("⚠️ Aucune date de fin trouvée dans l'événement");
        }


        if ($cancelAtPeriodEnd) {
            $email = $user->getEmail();

            $endsAtFormatted = $endsAt ? $endsAt->format('d/m/Y') : 'date inconnue';

            $this->mailgunService->send(
                $email,
                'Votre abonnement a été annulé',
                "Bonjour $email,\n\nVotre abonnement a bien été annulé. Vous aurez toujours accès jusqu’au $endsAtFormatted.\n\nMerci pour votre confiance !"
            );
            $this->logger->info("📧 Email envoyé pour l’annulation future à $email");
        } else {
            $this->logger->info("🔄 Aucune annulation en cours. Abonnement actif.");
        }

        $user->setSubscriptionPlan('pro');

        $this->em->flush();
        $this->logger->info("💾 Base de données mise à jour avec la modification d'abonnement.");

        return true;
    }


    private function handleDeleted($event): bool
    {
        $subscription = $event->data->object;
        $customerId = $subscription->customer ?? null;

        $this->logger->info("🔴 handleDeleted appelé pour customerId : $customerId");

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if (!$user) {
            $this->logger->warning("❌ Utilisateur non trouvé pour suppression customerId $customerId");
            return false;
        }
        $this->logger->info("✅ Utilisateur trouvé : id " . $user->getId());

        $user->setSubscriptionPlan('free');
        $user->setSubscriptionEndsAt(null);
        $user->setIsSubscriptionCanceled(true);
// flush etc

        $this->em->flush();
        $this->logger->info("💾 Abonnement supprimé, base mise à jour.");

        $email = $user->getEmail();
        $this->mailgunService->send(
            $email,
            'Votre abonnement a été annulé',
            "Bonjour $email,\n\nVotre abonnement a pris fin aujourd’hui. Vous n'avez plus accès à la modification de votre menu QR Code.\n\nMerci pour votre confiance !"
        );

        $this->logger->info("📧 Email final envoyé à $email");

        return true;
    }

    public function handleInvoicePaymentSucceeded($event): bool
    {
        $invoice = $event->data->object;
        $customerId = $invoice->customer ?? null;

        $this->logger->info("🔄 handleInvoicePaymentSucceeded appelé pour customerId : $customerId");

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if (!$user) {
            $this->logger->warning("❌ Utilisateur non trouvé pour customerId $customerId");
            return false;
        }
        $this->logger->info("✅ Utilisateur trouvé : id " . $user->getId());

        // On récupère d'abord l'id de subscription
        $subscriptionId = $invoice->subscription ?? null;

        if (!$subscriptionId && isset($invoice->parent->subscription_details->subscription)) {
            $subscriptionId = $invoice->parent->subscription_details->subscription;
        }

        $paymentMethodId = null;

        if ($subscriptionId) {
            try {
                $subscription = $this->stripeClient->subscriptions->retrieve($subscriptionId);
                $paymentMethodId = $subscription->default_payment_method ?? null;
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la récupération de la subscription Stripe: " . $e->getMessage());
            }
        }

        // Si pas trouvé via subscription, on regarde payment_intent ou payment_method dans la facture
        if (!$paymentMethodId) {
            if (!empty($invoice->payment_intent) && str_starts_with($invoice->payment_intent, 'pi_')) {
                try {
                    $paymentIntent = $this->stripeClient->paymentIntents->retrieve($invoice->payment_intent);
                    $paymentMethodId = $paymentIntent->payment_method ?? null;
                } catch (\Exception $e) {
                    $this->logger->error("Erreur lors de la récupération du PaymentIntent: " . $e->getMessage());
                }
            } elseif (!empty($invoice->payment_method) && str_starts_with($invoice->payment_method, 'pm_')) {
                // Si payment_method est déjà un id valide de payment method, on l'utilise directement
                $paymentMethodId = $invoice->payment_method;
            }
        }

        if (!$paymentMethodId) {
            $this->logger->warning("⚠️ Aucun payment_method trouvé dans la facture, le payment intent, ou la subscription.");
        }

        $periodEndTimestamp = $invoice->lines->data[0]->period->end ?? null;
        if (!$periodEndTimestamp) {
            $this->logger->warning("⚠️ Pas de période de fin trouvée dans l'invoice.");
            return false;
        }

        $endsAt = (new \DateTimeImmutable())
            ->setTimestamp($periodEndTimestamp)
            ->setTimezone(new \DateTimeZone('Europe/Paris'));

        $user->setSubscriptionEndsAt($endsAt);
        $user->setIsSubscriptionCanceled(false);

        $this->logger->info("📅 Date de fin de période mise à jour : " . $endsAt->format('Y-m-d H:i:s'));
        $this->logger->info("➡️ Customer ID : " . $customerId);
        $this->logger->info("➡️ Subscription ID : " . $subscriptionId);
        $this->logger->info("➡️ Payment Method ID : " . $paymentMethodId);
        $this->logger->info("➡️ Période de fin : " . $endsAt->format('Y-m-d H:i:s'));

        if ($paymentMethodId) {
            $this->syncPaymentMethodFromSubscriptionAndInvoice($subscriptionId, $invoice, $user);
        }

        $this->em->persist($user);
        $this->em->flush();

        $email = $user->getEmail();
        $this->mailgunService->send(
            $email,
            'Renouvellement d’abonnement réussi',
            "Bonjour,\n\nVotre abonnement a été renouvelé avec succès. Vous bénéficiez d’un accès jusqu’au " . $endsAt->format('d/m/Y') . ".\n\nMerci pour votre confiance !"
        );

        $this->logger->info("📧 Email de confirmation de renouvellement envoyé à $email");

        return true;
    }

    public function syncPaymentMethodFromSubscriptionAndInvoice($subscription, $invoice, $user): void
    {
        // Priorité au default_payment_method du customer sur la subscription
        $paymentMethodId = $subscription->default_payment_method ?? null;

        // Sinon fallback sur la méthode de paiement de la facture
        if (!$paymentMethodId) {
            if (!empty($invoice->payment_method)) {
                $paymentMethodId = $invoice->payment_method;
            } elseif (!empty($invoice->payment_intent)) {
                $paymentIntent = $this->stripeClient->paymentIntents->retrieve($invoice->payment_intent);
                $paymentMethodId = $paymentIntent->payment_method ?? null;
            }
        }

        if ($paymentMethodId && $user->getStripePaymentMethodId() !== $paymentMethodId) {
            $user->setStripePaymentMethodId($paymentMethodId);
            $this->em->flush();
        }
    }


}
