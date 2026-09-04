<?php

namespace App\Service;

use App\Entity\AuditLog; use App\Entity\Groupe; use App\Entity\GroupeMember; use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class GroupeService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly GroupeSlugGenerator $slugs) {}
    /** @param array<string,mixed> $data */
    public function create(User $creator, array $data): Groupe
    {
        if ($creator->getStatus() !== 'ACTIVE') throw new \LogicException('Ce compte ne peut pas créer de faction.');
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        return $this->em->wrapInTransaction(function () use ($creator, $data, $name): Groupe {
            $group = new Groupe($name, $this->slugs->generate($name), $creator, is_string($data['visibility'] ?? null) ? $data['visibility'] : Groupe::VISIBILITY_PUBLIC);
            $group->setDescription(is_string($data['description'] ?? null) ? $data['description'] : null);
            $group->setMedia(is_string($data['avatarUrl'] ?? null) ? $data['avatarUrl'] : null, is_string($data['bannerUrl'] ?? null) ? $data['bannerUrl'] : null);
            $this->em->persist($group); $this->em->persist(new GroupeMember($group, $creator, GroupeMember::OWNER)); $this->em->flush();
            return $group;
        });
    }
    /** @param array<string,mixed> $data */
    public function update(Groupe $g, array $data): Groupe
    {
        if (!$g->isActive()) throw new \LogicException('Cette faction est archivée.');
        if (array_key_exists('name',$data)) $g->rename(is_string($data['name']) ? $data['name'] : '');
        if (array_key_exists('description',$data)) $g->setDescription(is_string($data['description']) ? $data['description'] : null);
        if (array_key_exists('visibility',$data)) $g->setVisibility(is_string($data['visibility']) ? $data['visibility'] : '');
        if (array_key_exists('avatarUrl',$data) || array_key_exists('bannerUrl',$data)) $g->setMedia(is_string($data['avatarUrl'] ?? null) ? $data['avatarUrl'] : $g->getAvatarUrl(), is_string($data['bannerUrl'] ?? null) ? $data['bannerUrl'] : $g->getBannerUrl());
        $this->em->flush(); return $g;
    }
    public function archive(Groupe $g, User $actor): void
    {
        if (!$g->isActive()) throw new \LogicException('Cette faction est déjà archivée.');
        $this->em->wrapInTransaction(function () use ($g, $actor): void { $g->archive(); $this->em->persist(new AuditLog($actor, AuditLog::ACTION_FACTION_ARCHIVED, AuditLog::TARGET_TYPE_FACTION, $g->getId())); $this->em->flush(); });
    }
}
