<?php

namespace App\Support;

class MathCaptcha
{
    // En dessous de ce délai entre l'affichage et l'envoi, c'est un robot qui remplit le formulaire.
    private const MIN_SECONDS_TO_FILL = 3;

    /**
     * Génère une nouvelle question anti-spam (addition) et mémorise sa réponse en session.
     * Chaque formulaire a sa propre clé : deux formulaires peuvent cohabiter sur une même page.
     */
    public static function question(string $key, bool $en): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        session([$key => ['answer' => $a + $b, 'shown_at' => time()]]);

        return $en ? "How much is {$a} + {$b}?" : "Combien font {$a} + {$b} ?";
    }

    /**
     * Renvoie le message d'erreur à afficher, ou null si la réponse est acceptée.
     */
    public static function error(?array $captcha, mixed $answer, bool $en): ?string
    {
        if (!$captcha || (int) $answer !== $captcha['answer']) {
            return $en ? 'Wrong answer, please try again with the new question.' : 'Mauvaise réponse, réessayez avec la nouvelle question.';
        }
        if (time() - $captcha['shown_at'] < self::MIN_SECONDS_TO_FILL) {
            return $en ? 'Sent too quickly, please try again.' : 'Envoi trop rapide, merci de réessayer.';
        }

        return null;
    }
}
