<?php
// src/Controller/SubscriptionController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    #[Route('/subscription/cancel', name: 'subscription_cancel', methods: ['POST'])]
    public function cancel(EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $subscriptionId = $user->getStripeSubscriptionId();
        if (!$subscriptionId) {
            $this->addFlash('error', 'Aucun abonnement trouvé.');
            return $this->redirectToRoute('account');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            $subscription = Subscription::retrieve($subscriptionId);
            $subscription->cancel();
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('account');
        }

        // Mettre à jour l'utilisateur
        $user->setSubscriptionPlan('free');
        $user->setStripeSubscriptionId(null);
        $em->flush();

        $this->addFlash('success', 'Votre abonnement a été annulé.');
        return $this->redirectToRoute('account');
    }
}
