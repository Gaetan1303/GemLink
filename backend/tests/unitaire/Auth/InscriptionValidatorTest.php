<?php



namespace App\Tests\Unitaire\Auth;

use App\Validator\InscriptionValidator;
use PHPUnit\Framework\TestCase;

final class InscriptionValidatorTest extends TestCase
{
    public function testLesChampsObligatoiresDoiventEtrePresentsEtValides(): void
    {
        // Le test décrit le contrat métier attendu pour le formulaire d'inscription.
        $validator = new InscriptionValidator();

        $resultat = $validator->validate([
            'username' => 'ab',
            'email' => 'adresse-invalide',
            'password' => 'weak',
        ]);

        self::assertFalse($resultat->isValid());
        self::assertSame(
            'Le pseudo doit contenir entre 3 et 30 caractères alphanumériques.',
            $resultat->getError('username')
        );
        self::assertSame(
            'L’adresse email doit respecter le format RFC 5322.',
            $resultat->getError('email')
        );
        self::assertSame(
            'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.',
            $resultat->getError('password')
        );
    }

    public function testLeMotDePasseDoitRespecterLaPolitiqueDeSecuriteEtUtiliserArgon2id(): void
    {
        // On couvre ici le critère serveur le plus sensible : la politique de mot de passe.
        $validator = new InscriptionValidator();

        $resultat = $validator->validate([
            'username' => 'utilisateur123',
            'email' => 'utilisateur@example.com',
            'password' => 'Motdepasse1!',
        ]);

        self::assertTrue($resultat->isValid());
        self::assertSame('argon2id', $resultat->getPasswordHashAlgorithm());
    }

    public function testUneAdresseEmailDejaPresenteRenvoieUnMessageGenerique(): void
    {
        // Le message doit rester volontairement flou pour éviter l’énumération de comptes.
        $validator = new InscriptionValidator();

        $resultat = $validator->validate([
            'username' => 'nouvelutilisateur',
            'email' => 'existant@example.com',
            'password' => 'Motdepasse1!',
        ], emailDejaEnBase: true);

        self::assertFalse($resultat->isValid());
        self::assertSame(
            'Impossible de créer le compte avec ces informations.',
            $resultat->getGlobalError()
        );
    }
}
