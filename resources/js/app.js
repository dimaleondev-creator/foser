import './bootstrap';
import '../css/organization.css';
import './organization';
import {
	Activity,
	Bell,
	BookOpen,
	Building2,
	CalendarDays,
	ChartNoAxesColumnIncreasing,
	ClipboardCheck,
	CirclePlus,
	CreditCard,
	FileText,
	FlaskConical,
	FolderKanban,
	LayoutDashboard,
	LogOut,
	Megaphone,
	MessagesSquare,
	Settings,
	UserRound,
	Wallet,
	createElement,
} from 'lucide';

const researcherIconNodes = {
	dashboard: LayoutDashboard,
	profile: UserRound,
	projects: FolderKanban,
	newProject: CirclePlus,
	calls: Megaphone,
	applications: ClipboardCheck,
	evaluations: ChartNoAxesColumnIncreasing,
	active: Activity,
	finance: Wallet,
	payments: CreditCard,
	documents: FileText,
	publications: BookOpen,
	notifications: Bell,
	calendar: CalendarDays,
	support: MessagesSquare,
	settings: Settings,
	logout: LogOut,
	funded: Building2,
};

const universityIconNodes = {
	dashboard: LayoutDashboard,
	institution: Building2,
	students: UserRound,
	applications: ClipboardCheck,
	laboratories: FlaskConical,
	imports: FileText,
	reports: ChartNoAxesColumnIncreasing,
	messages: MessagesSquare,
	users: UserRound,
	settings: Settings,
	logout: LogOut,
	active: Activity,
	review: ClipboardCheck,
	validated: BookOpen,
	beneficiaries: Wallet,
};

document.querySelectorAll('[data-rd-icon]').forEach((slot) => {
	const iconNode = researcherIconNodes[slot.dataset.rdIcon];
	if (!iconNode) return;
	slot.replaceChildren(createElement(iconNode, {
		width: 17,
		height: 17,
		strokeWidth: 1.8,
		class: 'rd-lucide-icon',
		'aria-hidden': 'true',
		focusable: 'false',
	}));
});

document.querySelectorAll('[data-ud-icon]').forEach((slot) => {
	const iconNode = universityIconNodes[slot.dataset.udIcon];
	if (!iconNode) return;
	slot.replaceChildren(createElement(iconNode, {
		width: 17,
		height: 17,
		strokeWidth: 1.8,
		class: 'ud-lucide-icon',
		'aria-hidden': 'true',
		focusable: 'false',
	}));
});

const studentMenuToggle = document.querySelector('.student-menu-toggle');
const studentSidebar = document.querySelector('.student-sidebar');
const studentMenuBackdrop = document.querySelector('.student-menu-backdrop');

const closeStudentMenu = () => {
	studentSidebar?.classList.remove('is-open');
	document.body.classList.remove('student-menu-open');
	studentMenuToggle?.setAttribute('aria-expanded', 'false');
};

studentMenuToggle?.addEventListener('click', () => {
	const isOpen = studentSidebar?.classList.toggle('is-open') ?? false;
	document.body.classList.toggle('student-menu-open', isOpen);
	studentMenuToggle.setAttribute('aria-expanded', String(isOpen));
});

studentMenuBackdrop?.addEventListener('click', closeStudentMenu);
studentSidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeStudentMenu));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && studentSidebar?.classList.contains('is-open')) {
		closeStudentMenu();
		studentMenuToggle?.focus();
	}
});
window.addEventListener('resize', () => {
	if (window.innerWidth > 760) closeStudentMenu();
});

const researcherMenuToggle = document.querySelector('.rd-menu-toggle');
const researcherSidebar = document.querySelector('.rd-sidebar');
const researcherMenuBackdrop = document.querySelector('.rd-menu-backdrop');

const closeResearcherMenu = () => {
	researcherSidebar?.classList.remove('is-open');
	document.body.classList.remove('rd-menu-open');
	researcherMenuToggle?.setAttribute('aria-expanded', 'false');
};

researcherMenuToggle?.addEventListener('click', () => {
	const isOpen = researcherSidebar?.classList.toggle('is-open') ?? false;
	document.body.classList.toggle('rd-menu-open', isOpen);
	researcherMenuToggle.setAttribute('aria-expanded', String(isOpen));
});

researcherMenuBackdrop?.addEventListener('click', closeResearcherMenu);
researcherSidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeResearcherMenu));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && researcherSidebar?.classList.contains('is-open')) {
		closeResearcherMenu();
		researcherMenuToggle?.focus();
	}
});
window.addEventListener('resize', () => {
	if (window.innerWidth > 760) closeResearcherMenu();
});

const universityMenuToggle = document.querySelector('.ud-menu-toggle');
const universitySidebar = document.querySelector('.ud-sidebar');
const universityMenuBackdrop = document.querySelector('.ud-menu-backdrop');
const universityMobileQuery = window.matchMedia('(max-width: 760px)');

if (universitySidebar) universitySidebar.inert = universityMobileQuery.matches;

const closeUniversityMenu = () => {
	universitySidebar?.classList.remove('is-open');
	document.body.classList.remove('ud-menu-open');
	universityMenuToggle?.setAttribute('aria-expanded', 'false');
	universityMenuToggle?.setAttribute('aria-label', 'Ouvrir la navigation');
	if (universitySidebar) universitySidebar.inert = universityMobileQuery.matches;
};

universityMenuToggle?.addEventListener('click', () => {
	const isOpen = universitySidebar?.classList.toggle('is-open') ?? false;
	document.body.classList.toggle('ud-menu-open', isOpen);
	universityMenuToggle.setAttribute('aria-expanded', String(isOpen));
	universityMenuToggle.setAttribute('aria-label', isOpen ? 'Fermer la navigation' : 'Ouvrir la navigation');
	if (universitySidebar) universitySidebar.inert = universityMobileQuery.matches && !isOpen;
	if (isOpen) universitySidebar?.querySelector('a')?.focus();
});

universityMenuBackdrop?.addEventListener('click', closeUniversityMenu);
universitySidebar?.querySelectorAll('a, button').forEach((link) => link.addEventListener('click', () => {
	closeUniversityMenu();
	universityMenuToggle?.focus();
}));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && universitySidebar?.classList.contains('is-open')) {
		closeUniversityMenu();
		universityMenuToggle?.focus();
		return;
	}
	if (event.key === 'Tab' && universitySidebar?.classList.contains('is-open')) {
		const focusable = universitySidebar.querySelectorAll('a[href], button:not([disabled])');
		const first = focusable[0];
		const last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last?.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first?.focus();
		}
	}
});
window.addEventListener('resize', () => {
	if (!universityMobileQuery.matches) {
		closeUniversityMenu();
		if (universitySidebar) universitySidebar.inert = false;
	}
});
universityMobileQuery.addEventListener('change', (event) => {
	if (universitySidebar) universitySidebar.inert = event.matches;
	closeUniversityMenu();
});

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

const statisticValues = document.querySelectorAll('.home-page .stat-value[data-count]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (statisticValues.length && !reducedMotion && 'IntersectionObserver' in window) {
	const statisticObserver = new IntersectionObserver((entries, observer) => {
		entries.forEach((entry) => {
			if (!entry.isIntersecting) return;
			const element = entry.target;
			const target = Number(element.dataset.count);
			const startedAt = performance.now();
			const update = (now) => {
				const progress = Math.min((now - startedAt) / 850, 1);
				element.textContent = Math.round(target * (1 - (1 - progress) ** 3)).toLocaleString('fr-FR');
				if (progress < 1) requestAnimationFrame(update);
			};
			requestAnimationFrame(update);
			observer.unobserve(element);
		});
	}, { threshold: 0.35 });
	statisticValues.forEach((element) => statisticObserver.observe(element));
}
