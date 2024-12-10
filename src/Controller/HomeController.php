<?php

namespace App\Controller;

use App\Entity\Room;
use App\Form\RoomType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\RoomRepository;

class HomeController extends AbstractController
{
    #[Route('', name: 'app_home', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        RoomRepository $roomRepo,
        EntityManagerInterface $entityManager,
        Security $security
        ): Response
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
        }
        
        $user = $security->getUser();
        $rooms = [];

        if ($user !== null) {
            $rooms = $security->getUser()->getRooms();
        }

        return $this->render('home/index.html.twig', [
            'form' => $form,
            'rooms' => $rooms,
        ]);
    }

}
