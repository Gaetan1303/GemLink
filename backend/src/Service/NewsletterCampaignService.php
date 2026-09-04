<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NewsletterCampaignService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $token,
        private readonly int $domainId,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getMailingLists(): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->baseUrl, '/') . "/{$this->domainId}/mailing_lists",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->token}",
                    ],
                ]
            );

            $data = $response->toArray();

            if (($data['result'] ?? null) !== 'success') {
                throw new \RuntimeException('Impossible de récupérer les listes de diffusion.');
            }

            return $data['data'];
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Erreur API Infomaniak : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<int> $mailingListIds
     *
     * @return array{id: int, subject: string, status_label: string}
     */
    public function createCampaign(
        string $subject,
        string $content,
        string $emailFromName,
        string $emailFromAddr,
        array $mailingListIds,
        string $lang = 'fr',
    ): array {
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->baseUrl, '/') . "/{$this->domainId}/campaigns",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->token}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'subject' => $subject,
                        'email_from_name' => $emailFromName,
                        'email_from_addr' => $emailFromAddr,
                        'lang' => $lang,
                        'content' => $content,
                        'mailing_list_ids' => $mailingListIds,
                    ],
                ]
            );

            $data = $response->toArray();

            if (($data['result'] ?? null) !== 'success') {
                throw new \RuntimeException('Échec de la création de la campagne newsletter.');
            }

            return $data['data'];
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Erreur API Infomaniak : ' . $e->getMessage(), 0, $e);
        }
    }
}
