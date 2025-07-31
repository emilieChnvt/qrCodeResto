<?php
// src/Command/CheckSubscriptionsCommand.php
namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckSubscriptionsCommand extends Command
{
    protected static $defaultName = 'app:check-subscriptions';

    private $userRepository;
    private $em;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em)
    {
        parent::__construct();

        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    protected function configure()
    {
        $this->setName('app:check-subscriptions');
        $this->setDescription('Check expired subscriptions and downgrade users to free.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupérer les utilisateurs dont l’abonnement est expiré
        $expiredUsers = $this->userRepository->findUsersWithExpiredSubscription();

        foreach ($expiredUsers as $user) {
            $user->setSubscriptionPlan('free');
            $output->writeln('Downgraded user '.$user->getEmail().' to free plan.');
        }

        $this->em->flush();

        $output->writeln('Done checking subscriptions.');

        return Command::SUCCESS;
    }
}
