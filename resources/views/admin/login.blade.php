@extends('layouts.app')

@section('title', 'Connexion Administration')

@section('content')
    <div
        style="max-width: 400px; margin: 150px auto; padding: 30px; background: rgba(255,255,255,0.05); border: 1px solid var(--gold, #d4af37); border-radius: 8px; backdrop-filter: blur(10px);">
        <h2 style="font-family: 'Cormorant Garamond', serif; color: #fff; font-size: 2rem; margin-bottom: 20px;">Espace
            Admin</h2>

        @if(session('error'))
            <p style="color: #f56565; font-size: 0.9rem;">{{ session('error') }}</p>
        @endif

        <form action="{{ route('admin.login.submit') }}" method="POST">
            @csrf
            <div style="margin-bottom: 20px;">
                <label style="display:block; color: #a0aec0; margin-bottom: 8px;">Mot de passe secret</label>
                <input type="password" name="password" required
                    style="width: 100%; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid #4a5568; color: #fff; border-radius: 4px;">
            </div>
            <button type="submit"
                style="background: var(--gold, #d4af37); color: #000; border: none; padding: 10px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%;">
                Se connecter
            </button>
        </form>
    </div>
@endsection