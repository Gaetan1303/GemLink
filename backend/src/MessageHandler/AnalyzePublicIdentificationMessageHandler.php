<?php
namespace App\MessageHandler;

use App\Dto\AiAnalysisResult;
use App\Entity\PublicIdentification;
use App\Exception\AiAnalysisException;
use App\Message\AnalyzePublicIdentificationMessage;
use App\Repository\PublicIdentificationRepository;
use App\Service\PublicIdentificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class AnalyzePublicIdentificationMessageHandler
{
    public function __construct(private readonly PublicIdentificationRepository $identifications, private readonly PublicIdentificationService $service, private readonly EntityManagerInterface $em, private readonly HttpClientInterface $http, private readonly string $aiServiceUrl, private readonly string $internalApiKey, private readonly string $mediaStorageMode, private readonly string $uploadDir, private readonly string $localPublicBaseUrl) {}
    public function __invoke(AnalyzePublicIdentificationMessage $message): void
    {
        $identification = $this->identifications->find(Uuid::fromString($message->getIdentificationId()));
        if (!$identification instanceof PublicIdentification || $identification->isExpired()) return;
        try {
            $result = $this->analyze($identification);
            $identification->markAnalyzed(['nom' => $result->getLabel(), 'categorie' => $result->getCategory(), 'durete' => $result->getHardnessValue(), 'systemeCristallin' => $result->getCrystalSystem(), 'composition' => $result->getComposition(), 'description' => $result->getDescription(), 'confidence' => $result->getConfidence(), 'detectorConfidence' => $result->getDetectorConfidence(), 'isHighConfidence' => $result->getConfidence() >= .75, 'detections' => $result->getDetections(), 'modelVersion' => $result->getModelVersion()]);
            $this->em->flush();
            $this->service->releaseActiveLock($identification);
        } catch (\Throwable $exception) { throw $exception; }
    }
    private function analyze(PublicIdentification $identification): AiAnalysisResult
    {
        $content = $this->mediaStorageMode === 'local' ? $this->localContent($identification) : $this->http->request('GET', $identification->getMediaUrl())->getContent();
        $form = new FormDataPart(['file' => new DataPart($content, 'media', $identification->getMimeType())]);
        $response = $this->http->request('POST', rtrim($this->aiServiceUrl, '/') . '/analyze', ['headers' => array_merge($form->getPreparedHeaders()->toArray(), ['X-Internal-Key' => $this->internalApiKey]), 'body' => $form->bodyToIterable(), 'timeout' => 180]);
        if ($response->getStatusCode() >= 400) throw AiAnalysisException::invalidHttpStatus($response->getStatusCode());
        return AiAnalysisResult::fromArray($response->toArray());
    }
    private function localContent(PublicIdentification $identification): string
    {
        $relative = str_replace(rtrim($this->localPublicBaseUrl, '/') . '/', '', $identification->getMediaUrl());
        $content = @file_get_contents(rtrim($this->uploadDir, '/') . '/' . ltrim($relative, '/'));
        if ($content === false) throw AiAnalysisException::unreachableMedia($identification->getMediaUrl(), 404);
        return $content;
    }
}
