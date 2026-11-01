{{-- Appel à suggérer un sujet : $afterArticle adapte le texte quand le bloc suit un article publié --}}
@php
    $en = $lang === 'en';
    $suggestionErrors = $errors->getBag('suggestion');
    $suggestionOpen = session('suggestion_success') || $suggestionErrors->any();
@endphp

<section class="suggestion" id="suggestion">
    <div class="suggestion-card">
        <p class="suggestion-label">{{ $en ? 'Idea box' : 'Boîte à idées' }}</p>
        @if($afterArticle)
            <h2 class="suggestion-title">{{ $en ? 'Did you enjoy this article?' : 'Cet article vous a plu ?' }}</h2>
            <p class="suggestion-text">{{ $en ? 'Suggest other topics related to law and space.' : 'Suggérez d’autres sujets en lien avec le droit et l’espace.' }}</p>
        @else
            <h2 class="suggestion-title">{{ $en ? 'Is there a topic you would like to read about?' : 'Un sujet vous intrigue ?' }}</h2>
            <p class="suggestion-text">{{ $en ? 'Suggest a question about law and space for a future article.' : 'Proposez une question sur le droit et l’espace pour un prochain article.' }}</p>
        @endif

        <details class="suggestion-details" id="suggestion-details" @if($suggestionOpen) open @endif>
            <summary class="suggestion-cta">
                <span class="suggestion-cta-open">{{ $en ? 'Suggest a topic' : 'Suggérer un sujet' }}</span>
                <span class="suggestion-cta-close">{{ $en ? 'Close' : 'Refermer' }}</span>
            </summary>

            <form class="suggestion-form" id="suggestion-form" action="{{ route('suggestions.send') }}" method="POST" novalidate
                data-msg-sending="{{ $en ? 'Sending…' : 'Envoi en cours…' }}"
                data-msg-error="{{ $en ? 'Something went wrong. Please try again in a moment.' : 'Une erreur est survenue. Merci de réessayer dans un instant.' }}"
                data-msg-expired="{{ $en ? 'This page has been open for too long. Please reload it and try again.' : 'Cette page est ouverte depuis trop longtemps. Rechargez-la puis réessayez.' }}"
                data-msg-throttle="{{ $en ? 'Too many suggestions sent. Please wait a few minutes before trying again.' : 'Trop de suggestions envoyées. Patientez quelques minutes avant de réessayer.' }}">
                @csrf

                <div class="contact-success" id="suggestion-success" role="status" tabindex="-1" @unless(session('suggestion_success')) hidden @endunless>
                    <span class="contact-success-icon" aria-hidden="true">✓</span>
                    <div>
                        <p class="contact-success-title">{{ $en ? 'Suggestion received' : 'Suggestion reçue' }}</p>
                        <p class="contact-success-text" id="suggestion-success-text">{{ session('suggestion_success') }}</p>
                        <button type="button" class="contact-again" id="suggestion-again">{{ $en ? 'Suggest another topic' : 'Suggérer un autre sujet' }}</button>
                    </div>
                </div>

                <div id="suggestion-fields" @if(session('suggestion_success')) hidden @endif>
                    <p class="contact-alert" id="suggestion-alert" role="alert" @unless($suggestionErrors->has('suggestion')) hidden @endunless>{{ $suggestionErrors->first('suggestion') }}</p>

                    <div class="contact-field">
                        <label for="suggestion-idea">{{ $en ? 'Your idea' : 'Votre idée' }}</label>
                        <textarea id="suggestion-idea" name="idea" rows="4" required minlength="10" maxlength="2000"
                            placeholder="{{ $en ? 'E.g. Who is liable for damage caused by space debris?' : 'Ex. : qui est responsable des dommages causés par les débris spatiaux ?' }}"
                            @if($suggestionErrors->has('idea')) aria-invalid="true" @endif>{{ old('idea') }}</textarea>
                        <span class="contact-error" data-error-for="idea">{{ $suggestionErrors->first('idea') }}</span>
                    </div>

                    <div class="contact-field">
                        <label for="suggestion-email">{{ $en ? 'Your email address' : 'Votre adresse e-mail' }}</label>
                        <input type="email" id="suggestion-email" name="suggestion_email" value="{{ old('suggestion_email') }}" required maxlength="255" autocomplete="email"
                            placeholder="{{ $en ? 'name@example.com' : 'nom@exemple.fr' }}" aria-describedby="suggestion-privacy"
                            @if($suggestionErrors->has('suggestion_email')) aria-invalid="true" @endif>
                        <span class="contact-error" data-error-for="suggestion_email">{{ $suggestionErrors->first('suggestion_email') }}</span>
                        <small class="suggestion-privacy" id="suggestion-privacy">{{ $en ? 'Your email and your suggestion stay private: they are never displayed on the site.' : 'Votre adresse et votre suggestion restent privées : elles ne sont jamais affichées sur le site.' }}</small>
                    </div>

                    {{-- Champ piège : invisible pour les humains, rempli par les robots --}}
                    <div class="contact-trap" aria-hidden="true">
                        <label for="suggestion-website">Website</label>
                        <input type="text" id="suggestion-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="contact-captcha">
                        <div class="contact-field">
                            <label for="suggestion-captcha">
                                {{ $en ? 'Anti-spam check:' : 'Vérification anti-spam :' }}
                                <strong id="suggestion-captcha-question">{{ $suggestionCaptcha }}</strong>
                            </label>
                            <input type="text" id="suggestion-captcha" name="captcha" inputmode="numeric" pattern="[0-9]*" required autocomplete="off" maxlength="3"
                                @if($suggestionErrors->has('captcha')) aria-invalid="true" @endif>
                            <span class="contact-error" data-error-for="captcha">{{ $suggestionErrors->first('captcha') }}</span>
                        </div>
                        <button type="submit" class="contact-submit" id="suggestion-submit">{{ $en ? 'Send my idea' : 'Envoyer mon idée' }}</button>
                    </div>
                </div>
            </form>
        </details>
    </div>
</section>

<script>
    // Envoi de la suggestion sans recharger la page, avec un retour immédiat
    document.addEventListener('DOMContentLoaded', () => {
        const details = document.getElementById('suggestion-details');
        const form = document.getElementById('suggestion-form');
        const fields = document.getElementById('suggestion-fields');
        const success = document.getElementById('suggestion-success');
        const successText = document.getElementById('suggestion-success-text');
        const alertBox = document.getElementById('suggestion-alert');
        const submit = document.getElementById('suggestion-submit');
        const question = document.getElementById('suggestion-captcha-question');
        const submitLabel = submit.textContent;

        // À l'ouverture, on place le curseur directement dans le premier champ à remplir
        details.addEventListener('toggle', () => {
            if (details.open && !fields.hidden) (form.querySelector('[aria-invalid="true"]') || form.elements.idea).focus();
        });

        function showErrors(errors = {}) {
            form.querySelectorAll('[data-error-for]').forEach(el => {
                const message = errors[el.dataset.errorFor]?.[0] || '';
                el.textContent = message;
                form.elements[el.dataset.errorFor]?.setAttribute('aria-invalid', message ? 'true' : 'false');
            });
            form.querySelector('[aria-invalid="true"]')?.focus();
        }

        function showAlert(message) {
            alertBox.textContent = message || '';
            alertBox.hidden = !message;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            showAlert('');
            submit.disabled = true;
            submit.textContent = form.dataset.msgSending;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });

                if (response.status === 429 || response.status === 419) {
                    showAlert(response.status === 429 ? form.dataset.msgThrottle : form.dataset.msgExpired);
                    return;
                }

                const data = await response.json();
                // La question ne change que si elle a été utilisée : sinon la réponse saisie reste valable
                if (data.captcha) {
                    question.textContent = data.captcha;
                    form.elements.captcha.value = '';
                }

                if (data.ok) {
                    showErrors();
                    form.reset();
                    successText.textContent = data.message;
                    fields.hidden = true;
                    success.hidden = false;
                    success.focus();
                } else {
                    showErrors(data.errors);
                    if (!Object.keys(data.errors || {}).length) showAlert(data.message);
                }
            } catch (error) {
                showAlert(form.dataset.msgError);
            } finally {
                submit.disabled = false;
                submit.textContent = submitLabel;
            }
        });

        document.getElementById('suggestion-again').addEventListener('click', () => {
            success.hidden = true;
            fields.hidden = false;
            form.elements.idea.focus();
        });
    });
</script>
