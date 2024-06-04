<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ManagerRegistry;

class AuthController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $jwtManager;
    
    public function __construct(EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
    }
    
    
    #[Route('api/register', name: 'register', methods: 'post')]
    public function register(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate JSON data
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if (!$email || !$password) {
                return $this->json(['message' => 'Email and password are required'], 400);
            }

            // Check if the user already exists
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                return $this->json(['message' => 'User already exists with provided email'], 400);
            }

            // Create and persist the user
            $user = new User();
            $user->setEmail($email);
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );
            $user->setPassword($hashedPassword);
            $user->setUsername($email);

            $role = 'ROLE_USER';
            $adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['type' => 'ROLE_ADMIN']);
            if (!$adminUser) {
                $role = 'ROLE_ADMIN';
            }

            $user->setRoles([$role]); // You may want to handle roles differently
            $user->setType($role); // You may want to handle types differently
            
            
            $entityManager = $doctrine->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
            
            return $this->json(['message' => 'Registered Successfully'], 201);
        } catch (\Throwable $th) {
            return $this->json(['message' => 'An error occurred: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    #[Route('/api/login', name: 'user_login')]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        try {
            // Check if the request content is empty
            $content = $request->getContent();
            if (empty($content)) {
                return $this->json(['message' => 'Request content is empty'], Response::HTTP_BAD_REQUEST);
            }
            
            // Decode JSON content
            $data = json_decode($content, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(['message' => 'Invalid JSON format'], Response::HTTP_BAD_REQUEST);
            }
            
            // Find user by email
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            
            if ($user) {
                // Validate password
                if ($passwordHasher->isPasswordValid($user, $data['password'])) {
                    // Generate JWT token
                    $token = $this->jwtManager->create($user);
                    
                    $authUser = $this->entityManager->getRepository(Auth::class)->findOneBy(['user_id' => $user->getId()]);

                    $expiresAt = new \DateTime();
                    $expiresAt->modify('+60 minutes');

                    if(!$authUser) {
                        // Create Auth record
                        $auth = new Auth();         
                        $auth->setToken($token);         
                        $auth->setUserId($user->getId());         
                        $auth->setCreatedAt(new \DateTime());         
                        $auth->setExpiresAt($expiresAt);
                        $this->entityManager->persist($auth);
                    } else {
                        $authUser->setToken($token);  
                        $authUser->setCreatedAt(new \DateTime());  
                        $authUser->setExpiresAt($expiresAt);
                        $this->entityManager->persist($authUser);
                    }
                    
                    $this->entityManager->flush();
                    
                    return $this->json(['token' => $token]);
                }
            }
            
            // Invalid credentials
            return $this->json(['message' => 'Wrong username or password'], Response::HTTP_UNAUTHORIZED);
            
        } catch (\Throwable $th) {
            return $this->json(['message' => 'An error occurred: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
