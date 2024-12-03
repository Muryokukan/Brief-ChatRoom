<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Bundle\SecurityBundle\Security;

use App\Repository\RoomRepository;

class RoomController extends AbstractController
{
    #[Route('/room/{roomId}', name: 'app_room')]
    public function index(int $roomId, RoomRepository $roomRepo, Security $security): Response
    {
        $user = $security->getUser();

        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        
        $room = $roomRepo->find($roomId);

        $isMember = false;
        $userIdentifier = $user->getUserIdentifier();
        foreach ($room->getUsers() as $member) {
            if ($user->getUserIdentifier() == $userIdentifier) {
                $isMember = true;
                break;
            }
        }
        
        if (!$isMember) {
            return $this->redirectToRoute('app_home');
        }

        
        $messages = $room->getMessages();

        return $this->render('room/index.html.twig', [
            "roomId" => $roomId,
            "roomName" => $room->getName(),
            "messages" => $messages
        ]);
    }
}
