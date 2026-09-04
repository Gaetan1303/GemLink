<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\Report;
use App\Entity\User;
use App\Exception\DuplicateReportException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ReportService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function create(User $user, Publication $publication, string $reason, ?string $description): Report
    {
        $report = (new Report($user, $publication))
            ->setReasonType($reason)
            ->setDescription($description === null ? null : (trim($description) ?: null));

        try {
            $this->em->persist($report);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateReportException('Cette publication a déjà été signalée.', previous: $exception);
        }

        return $report;
    }
}
