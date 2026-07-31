<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class AdminDashboardService
{
    public function __construct(private readonly Connection $connection) {}

    /** @return array<string, mixed> */
    public function metrics(): array
    {
        $posts24h = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM publication WHERE created_at >= NOW() - INTERVAL '24 hours' AND deleted_at IS NULL");
        $analyses24h = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM publication WHERE status IN ('ANALYZED', 'COMMUNITY_VALIDATED') AND updated_at >= NOW() - INTERVAL '24 hours'");
        $activeUsers7d = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM utilisateur WHERE last_login_at >= NOW() - INTERVAL '7 days'");
        $communityRate = (float) $this->connection->fetchOne("SELECT COALESCE(AVG(CASE WHEN status = 'COMMUNITY_VALIDATED' THEN 1.0 ELSE 0.0 END), 0) FROM publication WHERE deleted_at IS NULL AND status IN ('ANALYZED', 'COMMUNITY_VALIDATED')");
        $minerals = $this->connection->fetchAllAssociative("SELECT pierre.name, COUNT(*) AS count FROM publication_pierre pp INNER JOIN pierre ON pierre.id = pp.pierre_id INNER JOIN publication p ON p.id = pp.publication_id WHERE p.deleted_at IS NULL GROUP BY pierre.id, pierre.name ORDER BY count DESC, pierre.name ASC LIMIT 10");
        $fineTuningJobs = $this->connection->fetchAllAssociative("SELECT j.id::text AS id, j.status, j.progress, v.name AS model_name FROM job_fine_tuning j INNER JOIN ai_model_version v ON v.id = j.version_modele_ia_id WHERE j.status IN ('PENDING', 'RUNNING') ORDER BY j.created_at DESC");

        return [
            'posts24h' => $posts24h,
            'aiAnalyses24h' => $analyses24h,
            'activeUsers7d' => $activeUsers7d,
            'communityValidationRate' => $communityRate,
            'topMinerals' => array_map(static fn (array $row) => ['name' => $row['name'], 'count' => (int) $row['count']], $minerals),
            'fineTuningJobs' => array_map(static fn (array $row) => ['id' => $row['id'], 'status' => strtolower($row['status']), 'progress' => (int) $row['progress'], 'modelVersion' => $row['model_name']], $fineTuningJobs),
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
