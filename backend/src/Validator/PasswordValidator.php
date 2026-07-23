<?php



namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class PasswordValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Password) {
            throw new UnexpectedTypeException($constraint, Password::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $errors = [];
        if (strlen($value) < 8) {
            $errors[] = '8 caractères minimum';
        }
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = '1 majuscule minimum';
        }
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = '1 minuscule minimum';
        }
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = '1 chiffre minimum';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
            $errors[] = '1 caractère spécial minimum';
        }

        if ($errors !== []) {
            $this->context->buildViolation(implode(', ', $errors))
                ->addViolation();
        }
    }
}
