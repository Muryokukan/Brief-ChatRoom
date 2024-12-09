<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Bundle\SecurityBundle\Security;

use LucianoTonet\GroqPHP\Groq;
use LucianoTonet\GroqPHP\GroqException;

use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use App\Entity\Room;
use App\Entity\User;
use App\Entity\Message;

#[Route("/groq")]
class GroqController extends AbstractController
{
    #[Route('/recap', name: 'groq_recap', methods: ["GET"])]
    public function recap(
        MessageRepository $messageRepo,
        UserRepository $userRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $entityManager,
        Request $request
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq();
        $context = $this->getContext($messageRepo, $roomRepo, "Donnes un résumé concis de leur conversation. Évites d'utiliser les #ids.", $chatroomId);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager);

        return new JsonResponse(
            json_encode(
                [
                    "status" => Response::HTTP_OK,
                    "answer" => $answer
                ]
            ),
            Response::HTTP_OK, [], true);
    }

    #[Route('/idea', name: 'groq_idea', methods: ["GET"])]
    public function idea(
        MessageRepository $messageRepo,
        UserRepository $userRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $entityManager,
        Request $request
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq();
        $context = $this->getContext($messageRepo, $roomRepo, "Donnes une idée ou piste de réflexion pour alimenter le brainstorming.", $chatroomId, messageHistory: 10);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager);

        return new JsonResponse(
            json_encode(
                [
                    "status" => Response::HTTP_OK,
                    "answer" => $answer
                ]
            ),
            Response::HTTP_OK, [], true);
    }

    #[Route('/critic', name: 'groq_critic', methods: ["GET"])]
    public function critic(
        MessageRepository $messageRepo,
        UserRepository $userRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $entityManager,
        Request $request,
        Security $security
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq();

        $asker = $security->getUser();
        $askerDisplay = $asker->getNickname()."#".$asker->getId();
        $context = $this->getContext($messageRepo, $roomRepo, $askerDisplay." te demande une critique constructive sur les dernières idées données.", $chatroomId, messageHistory: 6);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager);

        return new JsonResponse(
            json_encode(
                [
                    "status" => Response::HTTP_OK,
                    "answer" => $answer
                ]
            ),
            Response::HTTP_OK, [], true);
    }


    # Utility functions
    private function saveGroqMessage(
        string $message,
        Room $chatroom,
        User $groqAccount,
        EntityManagerInterface $entityManager
        )
    {
        $savedMessage = new Message();

        $savedMessage->setContent($message);
        $savedMessage->setRoom($chatroom);
        $savedMessage->setUser($groqAccount);

        $entityManager->persist($savedMessage);
        $entityManager->flush();
    }

    private function handleGroqError(GroqException $err) {
        $errlog = $err->getCode()." ".$err->getMessage()." ".$err->getType();
        
        if($err->getFailedGeneration()) {
            $errlog = $errlog." - ".$err->getFailedGeneration();
        }

        return new JsonResponse(
            json_encode([
                "status" => Response::HTTP_INTERNAL_SERVER_ERROR,
                "details" => $errlog
            ]),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            []
        );
    }

    private function makeGroq(?int $maxTokens = 128) : Groq {
        $groq = new Groq(
            $_ENV['GROQ_API_KEY'],
            [
                'temperature' => 0.5,
                'max_tokens' => $maxTokens,
            ]
        );

        return $groq;
    }

    private function getContext(
        MessageRepository $messageRepo,
        RoomRepository $roomRepo,
        String $prompt,
        int $chatroomId,
        ?int $messageHistory = 8
    ): array {
        $messages = $messageRepo->findBy(
            [
                "room" => $chatroomId
            ],
            [
                "id" => "DESC"
            ],
            $messageHistory
        );

        $messages = array_reverse($messages);

        $messagesGroq = [];

        foreach ($messages as $message) {
            $user = $message->getUser();
            $nickname = $user->getNickname();
            $userid = $user->getId();
            $content = $message->getContent();

            array_push($messagesGroq,
            [
                "role" => "user",
                "content" => $nickname."#".$userid.": ".$content
            ]
            );
        }

        $room = $roomRepo->find($chatroomId);
        $roomname = $room->getName();
        $roomdetails = $room->getDetails();

        array_push($messagesGroq,
        [
            'role' => 'system',
            'content' => "Réponds en français. Tu t'appelle CatBot.
                Tu es une IA qui aide ses utilisateurs à brainstorm dans des salons de chat. 
                Tu dois toujours rester concis, et ne pas te présenter sauf si demander. 
                Les utilisateurs sont identifiés par un surnom non-unique, suivi de leur identifiant. (Username#id) 
                Se baser sur l'identifiant pour reconnaître les ultilisateurs, mais répondre avec le même format. 
                La salle actuelle s'appelle \"".$roomname." \", avec comme description \"".$roomdetails."\". ".$prompt,
        ]    
        );

        return $messagesGroq;
    }

    private function generateGroq(Groq $groq, array $context) {
        $response = $groq->chat()->completions()->create([
            'model' => 'mixtral-8x7b-32768',
            'messages' => $context
        ]);
    
        return $response['choices'][0]['message']['content'];
    }
}
