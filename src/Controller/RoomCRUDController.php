<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Entity\Room;
use App\Repository\RoomRepository;

use App\Form\Room1Type;

#[Route('/room')]
final class RoomCRUDController extends AbstractController
{
    #[Route('/new', name: 'app_room_c_r_u_d_new', methods: ['GET', 'POST'])]
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

    #[Route('/{id}/show', name: 'app_room_c_r_u_d_show', methods: ['GET'])]
    public function show(Room $room): Response
    {
        return $this->render('room_crud/show.html.twig', [
            'room' => $room,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_room_c_r_u_d_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(Room1Type::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_room_c_r_u_d_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room_crud/edit.html.twig', [
            'room' => $room,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_room_delete', methods: ['POST'])]
    public function delete(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$room->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($room);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_room_c_r_u_d_index', [], Response::HTTP_SEE_OTHER);
    }
}
