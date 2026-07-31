<?php
namespace App\Message;
/** Message dédié à la file Redis basse priorité des visiteurs anonymes. */
final class AnalyzePublicIdentificationMessage
{
    public function __construct(private readonly string $identificationId) {}
    public function getIdentificationId(): string { return $this->identificationId; }
}
