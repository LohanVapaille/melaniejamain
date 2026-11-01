<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Support\MathCaptcha;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public static function captchaQuestion(bool $en): string
    {
        return MathCaptcha::question('contact_captcha', $en);
    }

    public function send(Request $request)
    {
        $en = session('lang', 'fr') === 'en';
        $captcha = session()->pull('contact_captcha');

        // Champ piège invisible : seul un robot le remplit. On fait mine d'avoir réussi.
        if (filled($request->input('website'))) {
            return $this->respond($request, $en, true, $this->successMessage($en));
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email:rfc|max:255',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
            'captcha' => 'required|integer',
        ], $en ? [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'This email address is not valid.',
            'subject.required' => 'Please enter a subject.',
            'subject.max' => 'The subject may not exceed 150 characters.',
            'message.required' => 'Please write your message.',
            'message.min' => 'Your message is too short (10 characters minimum).',
            'message.max' => 'Your message may not exceed 5,000 characters.',
            'captcha.required' => 'Please answer the anti-spam question.',
            'captcha.integer' => 'The answer must be a number.',
        ] : [
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
            'subject.required' => 'Indiquez l’objet de votre message.',
            'subject.max' => 'L’objet ne doit pas dépasser 150 caractères.',
            'message.required' => 'Écrivez votre message.',
            'message.min' => 'Votre message est trop court (10 caractères minimum).',
            'message.max' => 'Votre message ne doit pas dépasser 5 000 caractères.',
            'captcha.required' => 'Répondez à la question anti-spam.',
            'captcha.integer' => 'La réponse doit être un nombre.',
        ]);

        $validator->after(function ($validator) use ($request, $captcha, $en) {
            if ($validator->errors()->has('captcha')) {
                return;
            }
            if ($error = MathCaptcha::error($captcha, $request->input('captcha'), $en)) {
                $validator->errors()->add('captcha', $error);
            }
        });

        if ($validator->fails()) {
            return $this->respond($request, $en, false, $en ? 'Please correct the highlighted fields.' : 'Merci de corriger les champs indiqués.', $validator->errors()->toArray());
        }

        $recipient = config('mail.contact_to');
        $failure = $en
            ? 'Your message could not be sent. Please try again later.'
            : 'Votre message n’a pas pu être envoyé. Merci de réessayer plus tard.';

        if (!$recipient) {
            report(new \RuntimeException('CONTACT_MAIL_TO n’est pas défini dans le .env : message de contact non envoyé.'));
            return $this->respond($request, $en, false, $failure, [], 503);
        }

        try {
            Mail::to($recipient)->send(new ContactMessage(
                senderEmail: $request->input('email'),
                contactSubject: $request->input('subject'),
                contactMessage: $request->input('message'),
                language: $en ? 'en' : 'fr',
            ));
        } catch (\Throwable $e) {
            report($e);
            return $this->respond($request, $en, false, $failure, [], 503);
        }

        return $this->respond($request, $en, true, $this->successMessage($en));
    }

    private function successMessage(bool $en): string
    {
        return $en
            ? 'Thank you! Your message has been sent. You will receive a reply at the email address you provided.'
            : 'Merci ! Votre message a bien été envoyé. Vous recevrez une réponse à l’adresse e-mail indiquée.';
    }

    /**
     * Réponse JSON pour l'envoi instantané (fetch), redirection classique sinon.
     */
    private function respond(Request $request, bool $en, bool $ok, string $message, array $errors = [], ?int $status = null)
    {
        // Une question neuve à chaque tentative : une réponse ne sert qu'une fois.
        $question = self::captchaQuestion($en);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'errors' => $errors,
                'captcha' => $question,
            ], $status ?? ($ok ? 200 : 422));
        }

        $redirect = redirect()->to(Seo::homeUrl(session('lang', 'fr')) . '#contact');

        return $ok
            ? $redirect->with('contact_success', $message)
            : $redirect->withErrors($errors ?: ['contact' => $message], 'contact')->withInput($request->except('captcha', 'website'));
    }
}
