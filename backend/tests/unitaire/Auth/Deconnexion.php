<?php



namespace App\Tests\Unitaire\Auth;

/**
 * Objet de contrat pour US 1.5 — Déconnexion.
 *
 * Documente les invariants métier attendus, indépendamment de toute
 * implémentation technique. Utilisé comme référence dans les tests unitaires
 * et les tests de contrôleur.
 */
final class Deconnexion
{
    // ── Constantes du contrat ─────────────────────────────────────────────────

    /** CA-2 : TTL du JWT (durée de vie résiduelle utilisée comme TTL Redis). */
    public const JWT_TTL_SECONDS = 900;

    /** CA-1 : cookie contenant le refresh token httpOnly. */
    public const REFRESH_TOKEN_COOKIE_NAME = 'refresh_token';

    /** Message de confirmation renvoyé au client après déconnexion réussie. */
    public const MESSAGE_SUCCES = 'Déconnexion réussie.';

    private function __construct(
        /** CA-1 : le refresh token a-t-il été révoqué en base ? */
        public readonly bool $refreshTokenRevoqueEnBase,

        /** CA-1 : le cookie a-t-il été supprimé côté client (valeur vide + date passée) ? */
        public readonly bool $cookieSupprimeCoteClient,

        /** CA-2 : le JWT a-t-il été inscrit en blocklist Redis ? */
        public readonly bool $jwtInvalideLEnBloclistRedis,

        /** CA-2 : TTL de l'entrée Redis = durée résiduelle du JWT. */
        public readonly int $ttlBloclistSecondes,

        /** CA-3 : l'utilisateur est-il redirigé vers la page d'accueil ? */
        public readonly bool $redirigéVersAccueil,
    ) {}

    /**
     * Déconnexion nominale : toutes les garanties de sécurité sont respectées.
     */
    public static function reussite(int $ttlResiduelJwt): self
    {
        return new self(
            refreshTokenRevoqueEnBase:  true,
            cookieSupprimeCoteClient:   true,
            jwtInvalideLEnBloclistRedis: true,
            ttlBloclistSecondes:         $ttlResiduelJwt,
            redirigéVersAccueil:         true,
        );
    }

    /**
     * Déconnexion avec token absent ou déjà invalide : les garanties CA-3
     * restent assurées (l'utilisateur est quand même redirigé).
     */
    public static function avecTokenAbsent(): self
    {
        return new self(
            refreshTokenRevoqueEnBase:   false,
            cookieSupprimeCoteClient:    true,
            jwtInvalideLEnBloclistRedis: false,
            ttlBloclistSecondes:          0,
            redirigéVersAccueil:          true,
        );
    }

    /**
     * CA-2 : vérifie que la TTL Redis est cohérente avec la durée résiduelle du JWT.
     * On accepte une marge de 2 secondes pour absorber le temps d'exécution du test.
     */
    public function ttlEstCohérenteAvecExpiration(int $exp, int $maintenant): bool
    {
        $residuel = $exp - $maintenant;
        return abs($this->ttlBloclistSecondes - $residuel) <= 2;
    }
}