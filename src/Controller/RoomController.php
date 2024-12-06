<?php

namespace App\Controller;

use App\Entity\Room;
use App\Form\RoomType;
use App\Entity\Message;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Bundle\SecurityBundle\Security;

use App\Repository\RoomRepository;
use App\Repository\UserRepository;

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

    #[Route('/rendermessage', name: 'render_message', methods: ['GET'])]
    public function renderMessage(Request $request, UserRepository $userRepo) : Response
    {
        (int) $userid = $request->query->get('userid');
        $messcontent = $request->query->get('content');

        $nick = $userRepo->find($userid)->getNickname();

        $html = $this->render(
            'room/chatMessage.html.twig',
            [
                "nick" => $nick,
                'userid' => $userid,
                'messcontent' => $messcontent
            ]
        );
        
        return $html;
    }

    #[Route('/room/{roomId}/publish', name: 'room_publish', methods: ['POST'])]
    public function publish(
        int $roomId,
        HubInterface $hub,
        RoomRepository $roomRepo,
        Security $security,
        EntityManagerInterface $entityManager,
        Request $request
        ): Response
    {
        $requestArray = $request->toArray();

        // if($request->get('content') === null) {
        if(!isset($requestArray['content'])) {
            return new Response(
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_BAD_REQUEST,
                []
            );   
        }
        $content = $requestArray['content'];
        
        $user = $security->getUser();

        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$user->canAccessRoom($roomId)) {
            return new Response(
                Response::HTTP_FORBIDDEN,
                Response::HTTP_FORBIDDEN,
                []
            );
        }

        $message = new Message();

        $room = $roomRepo->find($roomId);

        $message->setContent($content);
        $message->setRoom($room);
        $message->setUser($user);

        $entityManager->persist($message);
        $entityManager->flush();

        $json = json_encode([
            'content' => $content,
            'user' => $user->getId()
        ]);

        $update = new Update(
            'room/'.$roomId,
            $json
        );

        $hub->publish($update);

        return new JsonResponse(
            $json,
            Response::HTTP_OK,
            [],
            true
        );
    }
}