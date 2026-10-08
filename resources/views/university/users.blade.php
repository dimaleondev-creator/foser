@extends('university.layout')

@section('content')
    <div class="student-welcome">
        <div>
            <p class="eyebrow">GESTION DES ACCÈS</p>
            <h1>Utilisateurs</h1>
            <p>Gérez les comptes habilités pour votre établissement.</p>
        </div>
    </div>

    @php($roleLabels = [
        'admin' => 'Administrateur',
        'admin_universite' => 'Administrateur université',
        'validateur' => 'Validateur',
        'gestionnaire_etudiants' => 'Gestionnaire des étudiants',
        'responsable_recherche' => 'Responsable recherche',
        'lecture_seule' => 'Lecture seule',
        'responsable' => 'Responsable',
    ])

    <div class="student-grid">
        <section class="student-panel">
            <h2>Comptes habilités</h2>
            @forelse($users as $user)
                <div class="application-item">
                    <div>
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }} · {{ $roleLabels[$user->role] ?? $user->role }}</small>
                    </div>
                    @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('university.users.remove', $user->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button button-dark" type="submit">Retirer l’accès</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="student-empty">Aucun autre compte n’est habilité pour le moment.</p>
            @endforelse
        </section>

        <section class="student-panel form-panel">
            <h2>Habiliter un compte</h2>
            @if($availableUsers->isEmpty())
                <p class="student-empty">Aucun compte établissement actif n’est disponible pour une nouvelle habilitation.</p>
            @else
                <form method="POST" action="{{ route('university.users.add') }}">
                    @csrf
                    <label for="user_id">Compte établissement</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Sélectionner un compte</option>
                        @foreach($availableUsers as $availableUser)
                            <option value="{{ $availableUser->id }}" @selected(old('user_id') == $availableUser->id)>{{ $availableUser->name }} · {{ $availableUser->email }}</option>
                        @endforeach
                    </select>
                    <label for="role">Rôle dans l’établissement</label>
                    <select id="role" name="role" required>
                        <option value="admin_universite">Administrateur université</option>
                        <option value="validateur">Validateur</option>
                        <option value="gestionnaire_etudiants">Gestionnaire des étudiants</option>
                        <option value="responsable_recherche">Responsable recherche</option>
                        <option value="lecture_seule">Lecture seule</option>
                    </select>
                    <button class="button button-dark" type="submit">Habiliter le compte</button>
                </form>
            @endif
        </section>
    </div>
@endsection