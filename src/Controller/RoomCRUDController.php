<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\SecurityBundle\Security;
use CoopTilleuls\UrlSignerBundle\UrlSigner\UrlSignerInterface;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Entity\Room;
use App\Entity\User;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;

use App\Form\Room1Type;

final class RoomCRUDController extends AbstractController
{
    #[Route('/adduser', name: 'crud_room_adduser', methods: ['GET'], defaults:["_signed" => true])]
    public function adduser(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security
        ): Response
    {
        $userId = $request->query->get("userId");

        $loggedUser = $security->getUser();

        // if (!$loggedUser->canAccessRoom($roomId)) {
        //     return $this->redirectToRoute('app_home');
        // }

        // TODO: Add adduser logic

        return $this->redirectToRoute('app_room_c_r_u_d_edit', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/newroom', name: 'app_room_c_r_u_d_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security
        ): Response
    {
        $room = new Room();
        $form = $this->createForm(Room1Type::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $room->addUser($security->getUser());
            $entityManager->persist($room);
            $entityManager->flush();

            return $this->redirectToRoute('app_room_c_r_u_d_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room_crud/new.html.twig', [
            'room' => $room,
            'form' => $form,
        ]);
    }

    #[Route('/room/{id}/show', name: 'app_room_c_r_u_d_show', methods: ['GET'])]
    public function show(Room $room): Response
    {
        return $this->render('room_crud/show.html.twig', [
            'room' => $room,
        ]);
    }

    #[Route('/room/{id}/edit', name: 'app_room_c_r_u_d_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Room $room,
        UserRepository $userRepo,
        EntityManagerInterface $entityManager,
        Security $security,
        UrlSignerInterface $urlSigner
        ): Response
    {
        if (!$security->getUser()->canAccessRoom($room->getId())) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(Room1Type::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_room_c_r_u_d_index', [], Response::HTTP_SEE_OTHER);
        }

        // Or $url = $this->generateUrl('secured_document', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL);
        $url = $this->generateUrl('crud_room_adduser', ['userid' => 2, 'roomid' => 3]);
        // Will expire after one hour.
        $expiration = (new \DateTime('now'))->add(new \DateInterval('PT24H'));
        // An integer can also be used for the expiration: it will correspond to a number of seconds. For 1 hour:
        // $expiration = 3600;

        // Not passing the second argument will use the default expiration time (86400 seconds by default).
        // return $this->urlSigner->sign($url);

        // Will return a path like this: /documents/42?expires=1611316656&signature=82f6958bd5c96fda58b7a55ade7f651fadb51e12171d58ed271e744bcc7c85c3
        // Or a URL depending on what has been signed before.
        $invitelink = $urlSigner->sign($url, $expiration);


        $usersInRoom = $room->getUsers();

        return $this->render('room_crud/edit.html.twig', [
            'room' => $room,
            'form' => $form,
            "usersInRoom" => $usersInRoom,
            "invitelink" => $invitelink
        ]);
    }

    #[Route('/room/{id}/delete', name: 'app_room_delete', methods: ['POST'])]
    public function delete(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$room->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($room);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_room_c_r_u_d_index', [], Response::HTTP_SEE_OTHER);
    }
}
