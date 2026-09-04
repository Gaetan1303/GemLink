<?php
namespace App\Service;
use App\Entity\PublicIdentification;
use App\Message\AnalyzePublicIdentificationMessage;
use App\Service\Media\MediaUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Redis;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

class PublicIdentificationService
{
    private const ACTIVE_TTL_SECONDS = 1800;
    public function __construct(private readonly EntityManagerInterface $em, private readonly MediaUploadService $media, private readonly MessageBusInterface $bus, private readonly Redis $redis) {}
    public function submit(string $requesterKey, UploadedFile $file): ?PublicIdentification
    {
        $lock = 'public-identification:active:' . $requesterKey;
        if (!$this->redis->set($lock, '1', ['nx', 'ex' => self::ACTIVE_TTL_SECONDS])) return null;
        try {
            $media = $this->media->uploadPublicImage($file, sprintf('identifications/%s', (new \DateTimeImmutable())->format('Y/m')));
            $identification = new PublicIdentification($requesterKey, $media->mediaUrl, $media->mimeType);
            $this->em->persist($identification); $this->em->flush();
            $this->bus->dispatch(new AnalyzePublicIdentificationMessage($identification->getId()->toRfc4122()));
            return $identification;
        } catch (\Throwable $exception) { $this->redis->del($lock); throw $exception; }
    }
    public function releaseActiveLock(PublicIdentification $identification): void { $this->redis->del('public-identification:active:' . $identification->getRequesterKey()); }
}
