<?php

namespace App\Controller;

use App\Command\PreRegisterCompanies;
use App\Message\CreateUser;
use App\Query\ListCompanies;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/users', name: 'user_list', methods: ['GET'])]
    public function list(): void
    {
    }

    #[Route(path: '/users/{id}', methods: ['GET', 'HEAD'])]
    public function show(): void
    {
    }

    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function create(): void
    {
        $this->bus->dispatch(new CreateUser('john@example.com'));
    }

    #[Route('/companies/pre-register', methods: ['POST'])]
    public function preRegister(): void
    {
        $command = new PreRegisterCompanies('user-1');
        $this->bus->dispatch($command);
    }
    #[Route('/companies', methods: ['GET'])]
    public function companies(): mixed
    {
        $query = new ListCompanies('user-1');

        return $this->handle($query);
    }

}
