<?php

declare(strict_types=1);

namespace App\Validator;

final class InscriptionValidator
{
    private const MESSAGE_PSEUDO = 'Le pseudo doit contenir entre 3 et 30 caractères alphanumériques.';
    private const MESSAGE_EMAIL = 'L’adresse email doit respecter le format RFC 5322.';
    private const MESSAGE_PASSWORD = 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    private const MESSAGE_GLOBAL = 'Impossible de créer le compte avec ces informations.';

    /**
     * @param array{username?: mixed, email?: mixed, password?: mixed} $donnees
     */
    public function validate(array $donnees, bool $emailDejaEnBase = false): InscriptionValidationResult
    {
        $erreurs = [];

        $pseudo = is_string($donnees['username'] ?? null) ? trim((string) $donnees['username']) : '';
        if (!$this->pseudoEstValide($pseudo)) {
            $erreurs['username'] = self::MESSAGE_PSEUDO;
        }

        $email = is_string($donnees['email'] ?? null) ? trim((string) $donnees['email']) : '';
        if (!$this->emailEstValide($email)) {
            $erreurs['email'] = self::MESSAGE_EMAIL;
        }

        $motDePasse = is_string($donnees['password'] ?? null) ? (string) $donnees['password'] : '';
        if (!$this->motDePasseEstValide($motDePasse)) {
            $erreurs['password'] = self::MESSAGE_PASSWORD;
        }

        if ($erreurs !== []) {
            return InscriptionValidationResult::invalid($erreurs);
        }

        if ($emailDejaEnBase) {
            return InscriptionValidationResult::invalid([], self::MESSAGE_GLOBAL);
        }

        $hash = password_hash($motDePasse, PASSWORD_ARGON2ID);

        if ($hash === false) {
            return InscriptionValidationResult::invalid([], self::MESSAGE_GLOBAL);
        }

        return InscriptionValidationResult::valid($hash, 'argon2id');
    }

    private function pseudoEstValide(string $pseudo): bool
    {
        return $pseudo !== '' && preg_match('/^[a-zA-Z0-9]{3,30}$/', $pseudo) === 1;
    }

    private function emailEstValide(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function motDePasseEstValide(string $motDePasse): bool
    {
        if (strlen($motDePasse) < 8) {
            return false;
        }

        $conditions = [
            preg_match('/[A-Z]/', $motDePasse) === 1,
            preg_match('/[a-z]/', $motDePasse) === 1,
            preg_match('/[0-9]/', $motDePasse) === 1,
            preg_match('/[^a-zA-Z0-9]/', $motDePasse) === 1,
        ];

        return !in_array(false, $conditions, true);
    }
}
