<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        $context = $this->getContext($messageRepo, "Donnes un résumé concis de leur conversation. Évites d'utiliser les #ids.", $chatroomId);

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

    private function makeGroq() : Groq {
        $groq = new Groq(
            $_ENV['GROQ_API_KEY'],
            [
                'temperature' => 0.5,
                'max_tokens' => 128,
            ]
        );

        return $groq;
    }

    private function getContext(
        MessageRepository $messageRepo,
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

        array_push($messagesGroq,
        [
            'role' => 'system',
            'content' => "Répondre en français. Tu t'appelle CatBot. ".$prompt,
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
