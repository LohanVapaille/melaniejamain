<?php

namespace App\Http\Controllers;

use App\Mail\SuggestionMessage;
use App\Support\MathCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SuggestionController extends Controller
{
    private const CAPTCHA_KEY = 'suggestion_captcha';
    private const IDEA_MAX_LENGTH = 2000;

    public static function captchaQuestion(bool $en): string
    {
        return MathCaptcha::question(self::CAPTCHA_KEY, $en);
    }

    /**
     * Suggestion envoyée par un visiteur depuis le site.
     */
    public function send(Request $request)
    {
        $en = session('lang', 'fr') === 'en';

        // Champ piège invisible : seul un robot le remplit. On fait mine d'avoir réussi.
        if (filled($request->input('website'))) {
            return $this->respond($request,true, $this->successMessage($en), [], null, self::captchaQuestion($en));
        }

        $validator = Validator::make($request->all(), [
            'suggestion_email' => 'required|email:rfc|max:255',
            'idea' => 'required|string|min:10|max:'.self::IDEA_MAX_LENGTH,
            'captcha' => 'required|integer',
        ], $en ? [
            'suggestion_email.required' => 'Please enter your email address.',
            'suggestion_email.email' => 'This email address is not valid.',
            'idea.required' => 'Please describe your idea.',
            'idea.min' => 'Your idea is too short (10 characters minimum).',
            'idea.max' => 'Your idea may not exceed 2,000 characters.',
            'captcha.required' => 'Please answer the anti-spam question.',
            'captcha.integer' => 'The answer must be a number.',
        ] : [
            'suggestion_email.required' => 'Indiquez votre adresse e-mail.',
            'suggestion_email.email' => 'Cette adresse e-mail n’est pas valide.',
            'idea.required' => 'Décrivez votre idée.',
            'idea.min' => 'Votre idée est trop courte (10 caractères minimum).',
            'idea.max' => 'Votre idée ne doit pas dépasser 2 000 caractères.',
            'captcha.required' => 'Répondez à la question anti-spam.',
            'captcha.integer' => 'La réponse doit être un nombre.',
        ]);

        // Une faute sur un autre champ ne consomme pas la question : le visiteur garde sa réponse.
        if ($validator->fails()) {
            return $this->respond($request,false, $this->correctMessage($en), $validator->errors()->toArray());
        }

        // Une réponse ne sert qu'une fois : dès qu'elle est vérifiée, on pose une question neuve.
        $captchaError = MathCaptcha::error(session()->pull(self::CAPTCHA_KEY), $request->input('captcha'), $en);
        $question = self::captchaQuestion($en);

        if ($captchaError) {
            return $this->respond($request,false, $this->correctMessage($en), ['captcha' => [$captchaError]], null, $question);
        }

        try {
            DB::table('suggestions')->insert([
                'email' => $request->input('suggestion_email'),
                'idea' => $request->input('idea'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $failure = $en
                ? 'Your suggestion could not be saved. Please try again later.'
                : 'Votre suggestion n’a pas pu être enregistrée. Merci de réessayer plus tard.';

            return $this->respond($request,false, $failure, [], 503, $question);
        }

        $this->notifyByMail($request->input('suggestion_email'), $request->input('idea'), $en);

        return $this->respond($request,true, $this->successMessage($en), [], null, $question);
    }

    /**
     * La suggestion est déjà enregistrée : un échec d'envoi est journalisé sans alerter le visiteur.
     */
    private function notifyByMail(string $email, string $idea, bool $en): void
    {
        $recipient = config('mail.contact_to');

        if (!$recipient) {
            report(new \RuntimeException('CONTACT_MAIL_TO n’est pas défini dans le .env : notification de suggestion non envoyée.'));
            return;
        }

        try {
            Mail::to($recipient)->send(new SuggestionMessage(
                senderEmail: $email,
                idea: $idea,
                language: $en ? 'en' : 'fr',
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Ajout ou modification depuis l'administration, sans captcha.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:suggestions,id',
            'email' => 'nullable|email:rfc|max:255',
            'idea' => 'required|string|max:'.self::IDEA_MAX_LENGTH,
        ], [
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
            'idea.required' => 'L’idée est obligatoire.',
            'idea.max' => 'L’idée ne doit pas dépasser 2 000 caractères.',
        ]);

        $id = $request->input('id');
        $fields = [
            'email' => $request->input('email'),
            'idea' => $request->input('idea'),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('suggestions')->where('id', $id)->update($fields);
            $message = 'La suggestion a bien été mise à jour !';
        } else {
            DB::table('suggestions')->insert($fields + ['created_at' => now()]);
            $message = 'La suggestion a bien été ajoutée !';
        }

        return redirect()->to(route('admin.dashboard').'#suggestions')->with('success', $message);
    }

    public function delete($id)
    {
        DB::table('suggestions')->where('id', $id)->delete();

        return redirect()->to(route('admin.dashboard').'#suggestions')->with('success', 'La suggestion a bien été supprimée.');
    }

    private function successMessage(bool $en): string
    {
        return $en
            ? 'Thank you! Your suggestion has been received and may inspire a future article.'
            : 'Merci ! Votre suggestion a bien été reçue et inspirera peut-être un prochain article.';
    }

    private function correctMessage(bool $en): string
    {
        return $en ? 'Please correct the highlighted fields.' : 'Merci de corriger les champs indiqués.';
    }

    /**
     * Réponse JSON pour l'envoi instantané (fetch), redirection classique sinon.
     */
    private function respond(Request $request, bool $ok, string $message, array $errors = [], ?int $status = null, ?string $question = null)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'errors' => $errors,
                'captcha' => $question,
            ], $status ?? ($ok ? 200 : 422));
        }

        $redirect = redirect()->to(url()->previous('/').'#suggestion');

        return $ok
            ? $redirect->with('suggestion_success', $message)
            : $redirect->withErrors($errors ?: ['suggestion' => $message], 'suggestion')->withInput($request->only('suggestion_email', 'idea'));
    }
}
