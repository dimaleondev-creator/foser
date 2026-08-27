import './bootstrap';
import '../css/organization.css';
import './organization';

const menuToggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');

const closeNavigation = () => {
	navigation?.classList.remove('is-open');
	menuToggle?.setAttribute('aria-expanded', 'false');
};

menuToggle?.addEventListener('click', () => {
	const isOpen = navigation?.classList.toggle('is-open') ?? false;
	menuToggle.setAttribute('aria-expanded', String(isOpen));
});

navigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeNavigation));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') closeNavigation();
});
window.addEventListener('resize', () => {
	if (window.innerWidth > 700) closeNavigation();
});

const siteHeader = document.querySelector('.site-header');
const updateHeaderState = () => siteHeader?.classList.toggle('is-scrolled', window.scrollY > 8);
window.addEventListener('scroll', updateHeaderState, { passive: true });
updateHeaderState();

const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
navigation?.querySelectorAll('[data-nav-path]').forEach((link) => {
	const path = link.getAttribute('data-nav-path');
	const active = path === '/' ? currentPath === '/' : currentPath === path || currentPath.startsWith(`${path}/`);
	link.classList.toggle('active', active);
	if (active) link.setAttribute('aria-current', 'page');
	else link.removeAttribute('aria-current');
});

const regionalMap = document.querySelector('[data-regional-map]');
const regionSelect = regionalMap?.querySelector('[data-region-select]');

if (regionalMap && regionSelect) {
	fetch('/api/v1/statistics/regions')
		.then((response) => response.ok ? response.json() : Promise.reject())
		.then(({ data }) => {
			regionSelect.replaceChildren(new Option('Choisir une région', ''));
			data.forEach((region) => regionSelect.add(new Option(region.region, region.region)));
			regionSelect.addEventListener('change', () => {
				const selected = data.find((region) => region.region === regionSelect.value);
				regionalMap.querySelector('[data-region-beneficiaries]').textContent = selected?.beneficiaries ?? '—';
				regionalMap.querySelector('[data-region-applications]').textContent = selected?.applications ?? '—';
				regionalMap.querySelector('[data-region-projects]').textContent = selected?.funded_projects ?? '—';
			});
		})
		.catch(() => { regionSelect.replaceChildren(new Option('Statistiques indisponibles', '')); });
}
