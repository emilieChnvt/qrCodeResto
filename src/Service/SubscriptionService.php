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
        $this->logger->info("Type d'événement Stripe reçu : " . $event->type);

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
        $user->setIsSubscriptionCanceled($cancelAtPeriodEnd);

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
            // ✅ Comparaison pour éviter d’écraser une date plus récente
            if (
                !$user->getSubscriptionEndsAt() ||
                $user->getSubscriptionEndsAt() < $endsAt
            ) {
                $user->setSubscriptionEndsAt($endsAt);
                $this->logger->info("📅 Date de fin de période mise à jour : " . $endsAt->format('Y-m-d H:i:s'));
            } else {
                $this->logger->info("ℹ️ La date existante est plus récente ou égale, aucune mise à jour.");
            }
        } else {
            $this->logger->warning("⚠️ Aucune date de fin trouvée dans l'événement");
        }

        // Email uniquement si l'abonnement est annulé
        if ($cancelAtPeriodEnd) {
            $email = $user->getEmail();
            $endsAtFormatted = $endsAt ? $endsAt->format('d/m/Y') : 'date inconnue';

            try {
                $this->mailgunService->send(
                    $email,
                    'Votre abonnement a été annulé',
                    "Bonjour $email,\n\nVotre abonnement a bien été annulé. Vous aurez toujours accès jusqu’au $endsAtFormatted.\n\nMerci pour votre confiance !"
                );
            } catch (\Mailgun\Exception\HttpClientException $e) {
                if ($e->getCode() === 429) {
                    $this->logger->warning("⏳ Limite d'envois Mailgun atteinte, email non envoyé pour $email");
                } else {
                    throw $e;
                }
            }

            // Réinitialiser le plan à "pro"
            $user->setSubscriptionPlan('pro');
        }

        // ✅ Un seul flush global ici
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
        try {
            $this->mailgunService->send(
                $email,
                'Votre abonnement a été annulé',
                "Bonjour $email,\n\nVotre abonnement a pris fin aujourd’hui..."
            );
        } catch (\Mailgun\Exception\HttpClientException $e) {
            if ($e->getCode() === 429) {
                $this->logger->warning("Limite d'envois Mailgun atteinte, email non envoyé pour $email");
            } else {
                throw $e;
            }
        }


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
                $this->logger->info('⏳ Avant appel Stripe API...');

                // 🔥 C’est ici que tu insères ton appel à Stripe
                $subscription = $this->stripeClient->subscriptions->retrieve($subscriptionId);

                $this->logger->info('✅ Après appel Stripe API...');
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

        if ($paymentMethodId && isset($subscription)) {
            $this->syncPaymentMethodFromSubscriptionAndInvoice($subscription, $invoice, $user);
        }


        $this->em->persist($user);
        $this->em->flush();

        $email = $user->getEmail();
        $email = $user->getEmail();

        switch ($invoice->billing_reason) {
            case 'subscription_create':
                // ✨ Premier paiement → mail de bienvenue
                try {
                    $this->mailgunService->send(
                        $email,
                        'Bienvenue ! Votre abonnement est actif 🎉',
                        "Bonjour,\n\nMerci pour votre inscription ! Votre abonnement est désormais actif jusqu’au " . $endsAt->format('d/m/Y') . ".\n\nBonne utilisation 🚀"
                    );
                    $this->logger->info("📧 Email de bienvenue envoyé à $email");
                } catch (\Mailgun\Exception\HttpClientException $e) {
                    if ($e->getCode() === 429) {
                        $this->logger->warning("⏳ Limite Mailgun atteinte, email non envoyé pour $email");
                    } else {
                        throw $e;
                    }
                }
                break;

            case 'subscription_cycle':
                // 🔄 Renouvellement → mail de confirmation
                try {
                    $this->mailgunService->send(
                        $email,
                        'Renouvellement d’abonnement réussi ✅',
                        "Bonjour,\n\nVotre abonnement a été renouvelé avec succès. Vous bénéficiez d’un accès jusqu’au " . $endsAt->format('d/m/Y') . ".\n\nMerci pour votre confiance 🙏"
                    );
                    $this->logger->info("📧 Email de renouvellement envoyé à $email");
                } catch (\Mailgun\Exception\HttpClientException $e) {
                    if ($e->getCode() === 429) {
                        $this->logger->warning("⏳ Limite Mailgun atteinte, email non envoyé pour $email");
                    } else {
                        throw $e;
                    }
                }
                break;

            default:
                $this->logger->info("ℹ️ Aucun email envoyé pour billing_reason={$invoice->billing_reason}");
        }



        $this->logger->info("📧 Email de confirmation de renouvellement envoyé à $email");

        return true;
    }

    public function syncPaymentMethodFromSubscriptionAndInvoice($subscription, $invoice, $user): void
    {
        $this->logger->info("🔄 syncPaymentMethodFromSubscriptionAndInvoice appelée");

        $paymentMethodId = $subscription->default_payment_method ?? null;

        if (!$paymentMethodId) {
            if (!empty($invoice->payment_method)) {
                $paymentMethodId = $invoice->payment_method;
            } elseif (!empty($invoice->payment_intent)) {
                try {
                    $paymentIntent = $this->stripeClient->paymentIntents->retrieve($invoice->payment_intent);
                    $paymentMethodId = $paymentIntent->payment_method ?? null;
                } catch (\Exception $e) {
                    $this->logger->error("Erreur lors de la récupération du PaymentIntent dans sync: " . $e->getMessage());
                }
            }
        }

        if ($paymentMethodId && $user->getStripePaymentMethodId() !== $paymentMethodId) {
            $this->logger->info("✅ Mise à jour du stripe_payment_method_id : $paymentMethodId");
            $user->setStripePaymentMethodId($paymentMethodId);
            $this->em->flush();
        } else {
            $this->logger->info("ℹ️ Aucun changement de méthode de paiement nécessaire.");
        }
    }

    public function getSubscriptionsForCustomer(string $customerId): array
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $subscriptions = \Stripe\Subscription::all([
            'customer' => $customerId,
            'status' => 'all',
            'limit' => 10, // ou plus si besoin
        ]);

        return $subscriptions->data;
    }



}
