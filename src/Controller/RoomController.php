<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\MessageRepository;

class RoomController extends AbstractController
{
    #[Route('/room/{id}', name: 'app_room')]
    public function index(int $id, MessageRepository $messageRepo): Response
    {
        $messages = $messageRepo->findBy([
            "room" => $id
        ]);
        return $this->render('room/index.html.twig', [
            'id' => $id,
            "messages" => $messages
        ]);
    }
}
