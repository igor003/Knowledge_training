<?php

namespace App\Command;

use App\Domain\Role\Model\Role;
use App\Domain\Role\Repository\RoleRepository;
use App\Domain\User\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:ensure',
    description: 'Create or update an admin area user.',
)]
final class UserEnsureCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Login name.')
            ->addArgument('password', InputArgument::REQUIRED, 'Login password.')
            ->addArgument('email', InputArgument::OPTIONAL, 'User email.')
            ->addArgument('role', InputArgument::OPTIONAL, 'User role code.', Role::DEFAULT_ADMIN_CODE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = trim((string) $input->getArgument('name'));
        $password = (string) $input->getArgument('password');
        $email = trim((string) ($input->getArgument('email') ?? ''));
        $role = (string) $input->getArgument('role');

        if ($name === '' || $password === '') {
            $io->error('Name and password are required.');

            return Command::INVALID;
        }

        $roleModel = $this->roles->findByCode($role);

        if ($roleModel === null) {
            $io->error(sprintf('Role "%s" does not exist. Run app:migrate first.', $role));

            return Command::INVALID;
        }

        $email = $email !== '' ? $email : $this->defaultEmail($name);
        $user = $this->users->findByName($name);

        if ($user === null) {
            $user = $this->users->create($name, $email, $password, $roleModel);
            $io->success(sprintf('User "%s" created with role "%s".', $user->name, $user->role));

            return Command::SUCCESS;
        }

        $this->users->update($user, $name, $email, $roleModel, true);
        $this->users->setPassword($user, $password);
        $io->success(sprintf('User "%s" updated, activated, and password reset.', $user->name));

        return Command::SUCCESS;
    }

    private function defaultEmail(string $name): string
    {
        $localPart = strtolower((string) preg_replace('/[^a-z0-9._-]+/i', '.', trim($name)));
        $localPart = trim($localPart, '.-_');

        return ($localPart !== '' ? $localPart : 'user') . '@example.local';
    }
}
