<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserRepository $users, private readonly AvatarUploadService $avatars) {}

    /** @param array<string,mixed> $data */
    public function update(User $user, array $data, ?UploadedFile $avatar): User
    {
        if (array_key_exists('username', $data)) {
            $username = is_string($data['username']) ? trim($data['username']) : '';
            if (preg_match('/^[a-zA-Z0-9]{3,30}$/', $username) !== 1) throw new InvalidArgumentException('Le pseudo doit contenir entre 3 et 30 caractères alphanumériques.');
            $existing = $this->users->findOneByUsername($username);
            if ($existing && !$existing->getId()->equals($user->getId())) throw new InvalidArgumentException('Ce pseudo est déjà utilisé.');
            $user->setUsername($username);
        }
        if (array_key_exists('bio', $data)) {
            if ($data['bio'] !== null && !is_string($data['bio'])) throw new InvalidArgumentException('La bio doit être une chaîne de caractères.');
            $bio = is_string($data['bio'] ?? null) ? trim($data['bio']) : null;
            if ($bio !== null && mb_strlen($bio) > 500) throw new InvalidArgumentException('La bio ne peut pas dépasser 500 caractères.');
            $user->setBio($bio === '' ? null : $bio);
        }
        if ($avatar) $user->setAvatarUrl($this->avatars->upload($avatar));
        $this->em->flush();
        return $user;
    }
}
