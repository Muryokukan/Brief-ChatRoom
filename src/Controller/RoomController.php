<?php

namespace App\Controller;

use App\Entity\Room;
use App\Form\RoomType;
use App\Entity\Message;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Bundle\SecurityBundle\Security;

use App\Repository\RoomRepository;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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

        if (!$user->canAccessRoom($roomId)) {
            return $this->redirectToRoute('app_home');
        }

        
        $messages = $room->getMessages();

        $response = new Response();

        return $this->render('room/index.html.twig', [
            "roomId" => $roomId,
            "roomName" => $room->getName(),
            "messages" => $messages,
        ],
        response: $response
        );
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

    #[Route('/room/{id}/details', name: 'room_show')]
    public function show(Room $room): Response
    {
        return $this->render('room/show.html.twig', [
            'room' => $room,
        ]);
    }

    // TODO: Once done testing, remove the GET method.
    #[Route('/room/{roomId}/publish', name: 'room_publish', methods: ['GET', 'POST'])]
    public function publish(
        int $roomId,
        HubInterface $hub,
        RoomRepository $roomRepo,
        Security $security,
        EntityManagerInterface $entityManager
        ): Response
    {
        $user = $security->getUser();

        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$user->canAccessRoom($roomId)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $message = new Message();

        $room = $roomRepo->find($roomId);

        // TODO: Somehow get the content out of a post request.
        $content = 'SET CONTENT';
        $message->setContent($content);
        $message->setRoom($room);
        $message->setUser($user);

        $entityManager->persist($message);
        $entityManager->flush();

        $update = new Update(
            'room/'.$roomId,
            json_encode([
                'status' => Response::HTTP_CREATED,
                'content' => $content,
                'user' => $user->getId()
                ])
        );

        $hub->publish($update);

        // TODO: Might not need a response after all. Might need some checking ?...
        // Actually, could just return a json response using what's sent as an Update.
        $response = new Response('published!', Response::HTTP_CREATED);
        return $response;
    }
}