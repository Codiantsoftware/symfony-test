<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
* @Route("/api/user")
*/
class UserController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    
    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }
    
    
    #[Route('/api/create', name: 'user_create', methods: ['POST'])]
    public function create(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        try {
            // Check if the request content is empty
            $content = $request->getContent();
            if (empty($content)) {
                return $this->json(['message' => 'Request content is empty'], Response::HTTP_BAD_REQUEST);
            }
            
            // Decode JSON content
            $decoded = json_decode($content);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(['message' => 'Invalid JSON format'], Response::HTTP_BAD_REQUEST);
            }
            
            // Check if the user is an admin
            if ($this->getUser()->getType() !== 'ROLE_ADMIN') {
                return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
            
            $em = $doctrine->getManager();
            $email = $decoded->email;
            $plaintextPassword = $decoded->password;
            
            // Check if the user already exists
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                return $this->json(['message' => 'User already exists with provided email'], Response::HTTP_CONFLICT);
            }
            
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword(new User(), $plaintextPassword);
            
            // Create and persist the new user
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($email);
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $user->setType('ROLE_USER');

            $em->persist($user);
            $em->flush();
            
            return $this->json(['message' => 'User created successfully']);
            
        } catch (\Throwable $th) {
            return $this->json(['message' => 'An error occurred: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    #[Route('/api/view', name: 'user_view', methods: ['POST'])]
    public function view(User $user): Response
    {    
        try {
            // Check if the user is either the authenticated user or has admin role
            if ($this->getUser()->getType() == 'ROLE_ADMIN' || ($user == $this->getUser() && $this->getUser()->getType() == 'ROLE_USER')) {
                return $this->json($user->getEmail());
            } else if ($user !== $this->getUser()) {
                // If the user is not authorized to view this user's information
                return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
            
            // If none of the above conditions are met, return the user's information
            return $this->json($user);
        } catch (\Throwable $th) {
            // If an unexpected error occurs, return an error message
            return $this->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
    
    #[Route('/api/all_user', name: 'view_all_user', methods: ['POST'])]
    public function index(Request $request): Response
    {   
        try {
            $returnArr = [];
            // If the user has ROLE_ADMIN, return all users
            if ($this->getUser()->getType() == 'ROLE_ADMIN') {
                $users = $this->entityManager->getRepository(User::class)->findAll();
                if ($users) {
                    foreach ($users as $data) {  
                        $returnArr[] = $data->getEmail();
                    }
                } else {
                    // If no users are found, return an empty array
                    return $this->json([]);
                }
            } else {
                // If the user is not an admin, return access denied
                return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
            
            return $this->json($returnArr);
        } catch (\Throwable $th) {
            // If an unexpected error occurs, return an error message
            return $this->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }   
    }
    
    #[Route('/api/update', name: 'user_update', methods: 'put')]
    public function update(Request $request, User $user): Response
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
            
            // Check if the user exists
            if (!$user) {
                return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Check if the user is either the authenticated user or has admin role
            if($this->getUser()->getType() == 'ROLE_ADMIN' || ($user == $this->getUser() && $this->getUser()->getType() == 'ROLE_USER')){
                
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
                if ($existingUser && $existingUser !== $user) {
                    return $this->json(['message' => 'User already exists with provided email'], Response::HTTP_CONFLICT);
                }
                
                // Update user properties if provided
                if (isset($data['password'])) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
                }
                if (isset($data['email'])) {
                    $user->setEmail($data['email']);
                }
                $this->entityManager->flush();
                
                return $this->json(['message' => 'User updated successfully']);
            } else if ($user !== $this->getUser() && $this->getUser()->getType() !== 'ROLE_ADMIN') {
                return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $th) {
            return $this->json(['message' => 'An error occurred: '. $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/api/delete', name: 'user_delete', methods: ['DELETE'])]
    public function delete(User $user): Response
    {
        try {
            // Check if the user exists
            if (!$user) {
                return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Check if the user is either the authenticated user or has admin role
            if ($this->getUser()->getType() == 'ROLE_ADMIN' || ($user == $this->getUser() && $this->getUser()->getType() == 'ROLE_USER')) {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
                
                return $this->json(['message' => 'User deleted']);
            } else if ($user !== $this->getUser() && $this->getUser()->getType() !== 'ROLE_ADMIN') {
                return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $th) {
            return $this->json(['message' => $th->getMessage()]);
        }
    }
}