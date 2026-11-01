{{-- Version texte brut : pas d'échappement HTML, sinon « & » deviendrait « &amp; » --}}
Nouvelle suggestion de sujet

De : {!! $senderEmail !!}
Reçue le : {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH:mm') }}
Langue : {{ $language === 'en' ? 'Anglais' : 'Français' }}

{!! $idea !!}

--
Répondez simplement à cet e-mail : la réponse partira vers {!! $senderEmail !!}.
La suggestion est aussi disponible dans l'espace administrateur : {!! route('admin.dashboard') !!}#suggestions
