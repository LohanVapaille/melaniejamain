{{-- Version texte brut : pas d'échappement HTML, sinon « & » deviendrait « &amp; » --}}
Nouveau message depuis le site

De : {!! $senderEmail !!}
Objet : {!! $contactSubject !!}
Reçu le : {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH:mm') }}
Langue : {{ $language === 'en' ? 'Anglais' : 'Français' }}

{!! $contactMessage !!}

--
Répondez simplement à cet e-mail : la réponse partira vers {!! $senderEmail !!}.
