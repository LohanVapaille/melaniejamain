@extends('layouts.app')

@section('title', $info->hero_h1 . ' — Accueil')

@section('content')
    @php $en = $lang === 'en'; $text = fn($key) => $info->$key ?: DB::table('infos')->where('langue', 'fr')->value($key); @endphp

    <div class="page">

        <section id="accueil" style="border-top:none;">
            <p class="hero-eyebrow">{{ $info->prenom }} {{ $info->nom }} — {{ $info->status_pro }}</p>
            <h1 class="hero-title">{!! $text('hero_h1') !!}</h1>
            <p class="hero-subtitle">{{ $text('hero_slogan') }}</p>
            <div class="hero-tags">
                @foreach(['hero_tag1','hero_tag2','hero_tag3','hero_tag4'] as $tag) @if($text($tag))<span class="tag">{{ $text($tag) }}</span>@endif @endforeach
            </div>

            @if($articles->count() > 0)
                @php 
                                                                                                                                                                                                                                                                                                                    $dernierArticle = $articles->first();

                    $articleName = $en && $dernierArticle->nom_anglais ? $dernierArticle->nom_anglais : $dernierArticle->nom;
                    $articleContent = $en && $dernierArticle->contenu_anglais ? $dernierArticle->contenu_anglais : $dernierArticle->contenu;
                    // Preserve sentence boundaries between paragraphs before removing HTML tags.
                    $articleContent = preg_replace('/<\s*br\s*\/?>|<\s*\/\s*(p|div|li|h[1-6])\s*>/i', ' ', $articleContent);
                    $textePropre = html_entity_decode(strip_tags($articleContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $textePropre = preg_replace('/\s+/u', ' ', trim($textePropre));

                    $phrases = preg_split('/(?<=[.!?])\s+/', $textePropre, -1, PREG_SPLIT_NO_EMPTY);
                    $deuxPremieresPhrases = implode(' ', array_slice($phrases, 0, 2));
                @endphp
                <a href="{{ route('articles.show', $dernierArticle->id) }}" class="hero-article-preview">
                    <span class="preview-label">{{ $en ? 'Preview of the latest article' : 'Aperçu du dernier article' }}</span>
                    <h3 class="preview-title">{{ $articleName }}</h3>
                    <p class="preview-text">{{ $deuxPremieresPhrases }}</p>
                    <span class="preview-arrow">{{ $en ? 'Read the article →' : "Lire l'article →" }}</span>
                </a>
            @endif
        </section>

        <section id="apropos">
            <p class="section-label">{{ $en ? 'About' : 'À propos' }}</p>
            <h2 class="section-title">{{ $text('about_title') }}</h2>
            <div class="about-grid">
                <div class="about-text">
                    {!! $text('about_text') !!}
                </div>
                <div class="about-pillars">
                    <div class="pillar">
                    <p class="pillar-title">{{ $text('about_pillar_title1') }}</p>
                        <p class="pillar-text">{{ $text('about_pillar_content1') }}</p>
                    </div>
                    <div class="pillar">
                    <p class="pillar-title">{{ $text('about_pillar_title2') }}</p>
                        <p class="pillar-text">{{ $text('about_pillar_content2') }}</p>
                    </div>
                    <div class="pillar">
                    <p class="pillar-title">{{ $text('about_pillar_title3') }}</p>
                        <p class="pillar-text">{{ $text('about_pillar_content3') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="articles">
            <p class="section-label">{{ $en ? 'Articles' : 'Articles' }}</p>
            <h2 class="section-title">{{ $text('section_article_title') }}</h2>

            <div class="articles-list" id="articles-container">
                @foreach($articles as $index => $article)
                    {{-- On cache par défaut tous les articles au-delà du 3ème --}}
                    <a href="{{ route('articles.show', $article->id) }}"
                        class="article-item {{ $index >= 3 ? 'article-hidden' : '' }}"
                        style="text-decoration: none; color: inherit; display: {{ $index >= 3 ? 'none' : 'flex' }};">
                        <span class="article-date" style="{{ $article->is_published == 2 ? 'color: #87cefa;' : '' }}">
                            @if($article->is_published == 2)
                                {{ $en ? 'Coming soon' : 'À venir' }}
                            @else
                                {{ \Carbon\Carbon::parse($article->created_at)->locale('fr')->isoFormat('DD MMM YYYY') }}
                            @endif
                        </span>
                        <div>
                            <h3 class="article-title">{{ $en && $article->nom_anglais ? $article->nom_anglais : $article->nom }}</h3>
                            <p class="article-excerpt">
                                {{ Str::limit(html_entity_decode(strip_tags($en && $article->contenu_anglais ? $article->contenu_anglais : $article->contenu)), 150, '...') }}
                            </p>
                            <span class="article-tag">{{ $en ? 'Read more' : 'Lire la suite' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Boutons de contrôle (affichés seulement s'il y a plus de 3 articles) --}}
            @if($articles->count() > 3)
                <div class="articles-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                    <button id="btn-voir-plus" class="article-tag btnvoirplusonly btnvoirplusgroup"
                        onclick="chargerPlusArticles()">
                        {{ $en ? 'Read more (+3)' : 'Voir plus (+3)' }}
                    </button>
                    <button id="btn-voir-tout" class="article-tag btnvoirplusgroup" onclick="afficherToutArticles()">
                        {{ $en ? 'Show all' : 'Voir tout' }}
                    </button>
                </div>
            @endif
        </section>

        <section id="contact">
            <p class="section-label">{{ $en ? 'Contact' : 'Contact' }}</p>
            <h2 class="section-title">{{ $text('section_contact_title') }}</h2>
            <p class="contact-intro">{{ $text('section_contact_text') }}</p>

            <div class="contact-grid">
            @php $contactErrors = $errors->getBag('contact'); @endphp
            <form class="contact-form" id="contact-form" action="{{ route('contact.send') }}" method="POST" novalidate
                data-msg-sending="{{ $en ? 'Sending…' : 'Envoi en cours…' }}"
                data-msg-error="{{ $en ? 'Something went wrong. Please try again in a moment.' : 'Une erreur est survenue. Merci de réessayer dans un instant.' }}"
                data-msg-throttle="{{ $en ? 'Too many messages sent. Please wait a few minutes before trying again.' : 'Trop de messages envoyés. Patientez quelques minutes avant de réessayer.' }}">
                @csrf

                <div class="contact-success" id="contact-success" role="status" tabindex="-1" @unless(session('contact_success')) hidden @endunless>
                    <span class="contact-success-icon" aria-hidden="true">✓</span>
                    <div>
                        <p class="contact-success-title">{{ $en ? 'Message sent' : 'Message envoyé' }}</p>
                        <p class="contact-success-text" id="contact-success-text">{{ session('contact_success') }}</p>
                        <button type="button" class="contact-again" id="contact-again">{{ $en ? 'Send another message' : 'Envoyer un autre message' }}</button>
                    </div>
                </div>

                <div class="contact-fields" id="contact-fields" @if(session('contact_success')) hidden @endif>
                    <p class="contact-alert" id="contact-alert" role="alert" @unless($contactErrors->has('contact')) hidden @endunless>{{ $contactErrors->first('contact') }}</p>

                    <div class="contact-field">
                        <label for="contact-email">{{ $en ? 'Your email address' : 'Votre adresse e-mail' }}</label>
                        <input type="email" id="contact-email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="{{ $en ? 'name@example.com' : 'nom@exemple.fr' }}">
                        <span class="contact-error" data-error-for="email">{{ $contactErrors->first('email') }}</span>
                    </div>

                    <div class="contact-field">
                        <label for="contact-subject">{{ $en ? 'Subject' : 'Objet' }}</label>
                        <input type="text" id="contact-subject" name="subject" value="{{ old('subject') }}" required maxlength="150">
                        <span class="contact-error" data-error-for="subject">{{ $contactErrors->first('subject') }}</span>
                    </div>

                    <div class="contact-field">
                        <label for="contact-message">Message</label>
                        <textarea id="contact-message" name="message" rows="6" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                        <span class="contact-error" data-error-for="message">{{ $contactErrors->first('message') }}</span>
                    </div>

                    {{-- Champ piège : invisible pour les humains, rempli par les robots --}}
                    <div class="contact-trap" aria-hidden="true">
                        <label for="contact-website">Website</label>
                        <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="contact-captcha">
                        <div class="contact-field">
                            <label for="contact-captcha">
                                {{ $en ? 'Anti-spam check:' : 'Vérification anti-spam :' }}
                                <strong id="contact-captcha-question">{{ $captchaQuestion }}</strong>
                            </label>
                            <input type="text" id="contact-captcha" name="captcha" inputmode="numeric" pattern="[0-9]*" required autocomplete="off" maxlength="3">
                            <span class="contact-error" data-error-for="captcha">{{ $contactErrors->first('captcha') }}</span>
                        </div>
                        <button type="submit" class="contact-submit" id="contact-submit">{{ $en ? 'Send message' : 'Envoyer le message' }}</button>
                    </div>
                </div>
            </form>

            <div class="contact-links">
                @if($info->mail)
                    <a href="mailto:{{ $info->mail }}" class="contact-link">
                        <div>
                            <span class="contact-link-label">Email</span>
                            {{ $info->mail }}
                        </div>
                    </a>
                @endif
                @if($info->linkedin)
                    <a href="{{ $info->linkedin }}" target="_blank" class="contact-link">
                        <div>
                            <span class="contact-link-label">LinkedIn</span>
                            {{ $info->prenom }} {{ $info->nom }}
                        </div>
                    </a>
                @endif
            </div>
            </div>
        </section>


    </div>

    <script>
        // Envoi du formulaire de contact sans recharger la page, avec un retour immédiat
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('contact-form');
            if (!form) return;

            const fields = document.getElementById('contact-fields');
            const success = document.getElementById('contact-success');
            const successText = document.getElementById('contact-success-text');
            const alertBox = document.getElementById('contact-alert');
            const submit = document.getElementById('contact-submit');
            const question = document.getElementById('contact-captcha-question');
            const submitLabel = submit.textContent;

            function showErrors(errors = {}) {
                form.querySelectorAll('[data-error-for]').forEach(el => {
                    const message = errors[el.dataset.errorFor]?.[0] || '';
                    el.textContent = message;
                    form.elements[el.dataset.errorFor]?.setAttribute('aria-invalid', message ? 'true' : 'false');
                });
                const firstInvalid = form.querySelector('[aria-invalid="true"]');
                firstInvalid?.focus();
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

                    if (response.status === 429) {
                        showAlert(form.dataset.msgThrottle);
                        return;
                    }

                    const data = await response.json();
                    if (data.captcha) question.textContent = data.captcha;
                    form.elements.captcha.value = '';

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

            document.getElementById('contact-again').addEventListener('click', () => {
                success.hidden = true;
                fields.hidden = false;
                form.elements.email.focus();
            });
        });
    </script>


@endsection
