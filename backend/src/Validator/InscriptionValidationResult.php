<?php



namespace App\Validator;

final class InscriptionValidationResult
{
    /**
     * @param array<string, string> $errors
     */
    private function __construct(
        private readonly bool $valid,
        private readonly array $errors = [],
        private readonly ?string $globalError = null,
        private readonly ?string $passwordHash = null,
        private readonly ?string $passwordHashAlgorithm = null,
    ) {
    }

    /**
     * @param array<string, string> $errors
     */
    public static function invalid(array $errors, ?string $globalError = null): self
    {
        return new self(false, $errors, $globalError);
    }

    public static function valid(string $passwordHash, string $passwordHashAlgorithm): self
    {
        return new self(true, [], null, $passwordHash, $passwordHashAlgorithm);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function getGlobalError(): ?string
    {
        return $this->globalError;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getPasswordHashAlgorithm(): ?string
    {
        return $this->passwordHashAlgorithm;
    }
}
