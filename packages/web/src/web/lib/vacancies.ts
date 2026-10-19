// Вакансии ведутся в EspoCRM. На сайте их отдают api/vacancy-page.php (готовая страница)
// и api/vacancies.php (JSON для переходов без перезагрузки).
export interface VacancyQuestion { label: string; question: string; minLength: number }
export interface VacancySummary { slug: string; name: string; location: string; employment: string; salary: string; tags: string[] }
export interface Vacancy extends VacancySummary {
	description: string;
	duties: string[];
	requirements: string[];
	conditions: string[];
	requireHh: boolean;
	deadline: string;
	datePosted: string;
	questions: VacancyQuestion[];
}
interface Boot { list?: VacancySummary[]; vacancy?: Vacancy }

export const isServer = typeof window === "undefined";

// Заглушка для пререндера: api/vacancy-page.php вставляет сюда текст вакансии из CRM.
export const SERVER_SLOT = "<!--vac--><!--/vac-->";

let boot: Boot | null = null;
export function bootData(): Boot {
	if (isServer) return {};
	if (!boot) {
		try { boot = JSON.parse(document.getElementById("vac-data")?.textContent || "{}") as Boot; } catch { boot = {}; }
	}
	return boot ?? {};
}

export async function loadVacancies(): Promise<VacancySummary[]> {
	const pre = bootData().list;
	if (pre) return pre;
	const r = await fetch("/api/vacancies.php");
	if (!r.ok) throw new Error(`HTTP ${r.status}`);
	const d = (await r.json()) as { list?: VacancySummary[] };
	return d.list ?? [];
}

export async function loadVacancy(slug: string): Promise<Vacancy | null> {
	const pre = bootData().vacancy;
	if (pre && pre.slug === slug) return pre;
	const r = await fetch(`/api/vacancies.php?slug=${encodeURIComponent(slug)}`);
	if (r.status === 404) return null;
	if (!r.ok) throw new Error(`HTTP ${r.status}`);
	const d = (await r.json()) as { vacancy?: Vacancy };
	return d.vacancy ?? null;
}
