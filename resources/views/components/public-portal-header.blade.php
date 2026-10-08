@props(['active' => ''])
<header class="foser-site-header" data-foser-header>
    <div class="foser-topline"><div class="foser-shell foser-topline-inner"><span>RÉPUBLIQUE DU BURKINA FASO</span><a href="{{ route('contact') }}">Contact institutionnel <span aria-hidden="true">↗</span></a></div></div>
    <div class="foser-nav-surface">
        <div class="foser-shell foser-nav-wrap">
            <a class="foser-brand" href="{{ route('home') }}" aria-label="FOSER, accueil">
                <img src="{{ asset('images/logo-foser.png') }}" alt="FOSER" width="94" height="54">
                <span>Fonds de Soutien à l'Éducation<br>et à la Recherche</span>
            </a>
            <button class="foser-menu-toggle" type="button" aria-expanded="false" aria-controls="foser-primary-navigation" aria-label="Ouvrir le menu">
                <span aria-hidden="true">☰</span>
            </button>
            <nav class="foser-primary-nav" id="foser-primary-navigation" aria-label="Navigation principale" data-foser-navigation>
                <a @class(['is-active' => $active === 'home']) href="{{ route('home') }}">Accueil</a>
                <a @class(['is-active' => $active === 'institution']) href="{{ route('institution.index') }}">Le FOSER</a>
                <a @class(['is-active' => $active === 'programs']) href="{{ route('programs.index') }}">Nos programmes</a>
                <a href="{{ route('calls.index') }}">Appels à candidatures</a>
                <a href="{{ route('news.index') }}">Actualités</a>
                <a href="{{ route('media.index') }}">Médiathèque</a>
                <a href="{{ route('documents.index') }}">Centre documentaire</a>
                <a href="{{ route('faq') }}">FAQ</a>
                <a href="{{ route('contact') }}">Contact</a>
                <div class="foser-nav-account">
                    <a class="foser-login-link" href="{{ route('student.login') }}">Connexion</a>
                    <a class="foser-register-link" href="{{ route('student.register') }}">Créer un compte <span aria-hidden="true">↗</span></a>
                    <a class="foser-language-link" href="{{ route('language.switch', app()->getLocale() === 'fr' ? 'en' : 'fr') }}" aria-label="{{ app()->getLocale() === 'fr' ? 'Switch to English' : 'Passer en français' }}">{{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}</a>
                </div>
            </nav>
        </div>
    </div>
</header>
<script>
    (() => {
        const header = document.querySelector('[data-foser-header]');
        const toggle = header?.querySelector('.foser-menu-toggle');
        const navigation = header?.querySelector('[data-foser-navigation]');
        if (!header || !toggle || !navigation) return;
        const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });
        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Ouvrir le menu' : 'Fermer le menu');
            navigation.classList.toggle('is-open', !isOpen);
        });
        navigation.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Ouvrir le menu');
                navigation.classList.remove('is-open');
                toggle.focus();
            }
        });
    })();
</script>
