<?php

// src/Service/SubscriptionService.php

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubscriptionService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailgunService $mailgunService
    ) {}

    public function handleStripeEvent($event): bool
    {
        return match ($event->type) {
            'customer.subscription.created' => $this->handleCreated($event),
            'customer.subscription.updated' => $this->handleUpdated($event),
            'customer.subscription.deleted' => $this->handleDeleted($event),
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

        $priceId = $subscription->items->data[0]->price->id ?? null;
        $plan = $priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy' ? 'pro' : 'free';
        $user->setSubscriptionPlan($plan);
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
        }
        else {
            $this->logger->info("🔄 Aucune annulation en cours. Abonnement actif.");
        }

        $priceId = $subscription->items->data[0]->price->id ?? null;
        $plan = $priceId === 'price_1Rpsu506EEhfyUPZu7D2fhTy' ? 'pro' : 'free';
        $user->setSubscriptionPlan($plan);
        $this->logger->info("📦 Plan d'abonnement mis à jour à : $plan");

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
}
