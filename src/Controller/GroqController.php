<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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
        Request $request,
        HubInterface $hub
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq(temperature: 0.5, maxTokens: 256);
        $context = $this->getContext($messageRepo, $roomRepo, "Donnes un résumé concis de leur conversation. Évites d'utiliser les #ids.", $chatroomId, messageHistory: 12);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager, $hub);

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
        Request $request,
        HubInterface $hub
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq(temperature: 0.6, maxTokens: 128);
        $context = $this->getContext($messageRepo, $roomRepo, "Donnes une idée ou piste de réflexion pour alimenter le brainstorming.", $chatroomId, messageHistory: 8);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager, $hub);

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
        Security $security,
        HubInterface $hub
        ): JsonResponse
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $user = $userRepo->find(1);

        $groq = $this->makeGroq(temperature: 0.5, maxTokens: 256);

        $asker = $security->getUser();
        $askerDisplay = $asker->getNickname()."#".$asker->getId();
        $context = $this->getContext($messageRepo, $roomRepo, $askerDisplay." te demande une critique constructive sur les dernières idées données.", $chatroomId, messageHistory: 6);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager, $hub);

        return new JsonResponse(
            json_encode(
                [
                    "status" => Response::HTTP_OK,
                    "answer" => $answer
                ]
            ),
            Response::HTTP_OK, [], true);
    }

    #[Route('/custom', name: 'groq_custom', methods: ['POST'])]
    public function custom(
        MessageRepository $messageRepo,
        UserRepository $userRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $entityManager,
        Request $request,
        Security $security,
        HubInterface $hub
        ): Response
    {
        $chatroomId = $request->query->get("chatroom");
        $chatroom = $roomRepo->find($chatroomId);

        $requestArray = $request->toArray();

        if(!isset($requestArray['content'])) {
            return new Response(
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_BAD_REQUEST,
                []
            );   
        }
        $content = $requestArray['content'];
        
        $user = $security->getUser();
        
        if ($user === null || !$user->canAccessRoom($chatroomId)) {
            return new Response(
                Response::HTTP_FORBIDDEN,
                Response::HTTP_FORBIDDEN,
                []
            );
        }
        

        $user = $userRepo->find(1);

        $groq = $this->makeGroq(temperature: 0.5, maxTokens: 256);

        $asker = $security->getUser();
        $askerDisplay = $asker->getNickname()."#".$asker->getId();
        $context = $this->getContext(
            $messageRepo,
            $roomRepo,
            $askerDisplay." te demande : \"".$content."\". Récapitule très birèvement sa demande, et réponds-lui.",
            $chatroomId,
            messageHistory: 6);

        try {
            $answer = $this->generateGroq($groq, $context);
        } catch (GroqException $err) {
            return $this->handleGroqError($err);
        }

        $this->saveGroqMessage($answer, $chatroom, $user, $entityManager, $hub);

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
        EntityManagerInterface $entityManager,
        HubInterface $hub
        )
    {
        $savedMessage = new Message();

        $savedMessage->setContent($message);
        $savedMessage->setRoom($chatroom);
        $savedMessage->setUser($groqAccount);

        $entityManager->persist($savedMessage);
        $entityManager->flush();

        // To publish the update to the mercure hub
        $json = json_encode([
            'content' => $message,
            'user' => $groqAccount->getId()
        ]);

        $update = new Update(
            'room/'.$chatroom->getId(),
            $json
        );

        $hub->publish($update);
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

    private function makeGroq(?float $temperature = 0.5, ?int $maxTokens = 128) : Groq {
        $groq = new Groq(
            $_ENV['GROQ_API_KEY'],
            [
                'temperature' => $temperature,
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
            'content' => "Réponds OBLIGATOIREMENT en français. N'utilise pas ta propre mise en forme. Écris uniquement le contenu, sans nom. Tu t'appelle CatBot. Ne te réponds pas à toi-même, l'ID numéro #1 (ex. CATBOT#1). Ne parles pas en tant qu'utilisateur (Par exemple, \"Jean#5: [message en tant que jean]\" est interdit). Tu es une IA qui aide ses utilisateurs à brainstorm dans des salons de chat. Tu dois toujours rester concis. Les utilisateurs sont identifiés par un surnom non-unique, suivi de leur identifiant. (Username#id). Se baser sur l'identifiant pour reconnaître les utilisateurs. La salle actuelle s'appelle \"".$roomname." \", avec comme description \"".$roomdetails."\". ".$prompt,
        ]    
        );

        return $messagesGroq;
    }

    private function generateGroq(Groq $groq, array $context) {
        $response = $groq->chat()->completions()->create([
            'model' => 'llama-3.3-70b-versatile',
            // 'model' => 'mixtral-8x7b-32768',
            'messages' => $context
        ]);
    
        return $response['choices'][0]['message']['content'];
    }
}
