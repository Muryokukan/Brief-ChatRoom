<?php

namespace App\Controller;

use App\Entity\Room;
use App\Form\RoomType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/room/create', name: 'room_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $room = new Room();
        $form = $this->createForm(RoomType::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($room);
            $entityManager->flush();

            return $this->redirectToRoute('room_show', ['id' => $room->getId()]);
        }

        // Si la requête est POST mais le formulaire n'est pas valide, on traite comme dans la deuxième version
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $details = $request->request->get('details');

            $room->setName($name);
            $room->setDetails($details);

            $entityManager->persist($room);
            $entityManager->flush();

            return $this->redirectToRoute('room_show', ['id' => $room->getId()]);
        }

        return $this->render('room/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/room/{id}', name: 'room_show')]
    public function show(Room $room): Response
    {
        return $this->render('room/show.html.twig', [
            'room' => $room,
        ]);
    }
}