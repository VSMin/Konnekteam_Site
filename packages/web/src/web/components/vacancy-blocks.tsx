// Блоки страниц вакансий. Ту же разметку повторяет api/_vac.php — меняйте оба файла вместе.
import type { CSSProperties, ReactNode } from "react";
import { Link } from "wouter";
import type { Vacancy, VacancySummary } from "../lib/vacancies";

const pillStyle: CSSProperties = { background: "rgba(227,30,36,0.1)", border: "1px solid rgba(227,30,36,0.3)", borderRadius: 20, padding: "6px 16px", fontSize: "0.85rem", color: "var(--accent)" };
const textColor = "rgba(255,255,255,0.75)";

export const vacancyMeta = (v: Pick<VacancySummary, "location" | "employment">) => [v.location, v.employment].filter(Boolean).join(" · ");
export const vacancyPills = (v: Pick<VacancySummary, "salary" | "tags">) => [v.salary, ...v.tags].filter(Boolean);
export const formatDate = (d: string) => d.slice(0, 10).split("-").reverse().join(".");

export function Pills({ items, center = true }: { items: string[]; center?: boolean }) {
	if (!items.length) return null;
	return (
		<div style={{ display: "flex", gap: 12, justifyContent: center ? "center" : "flex-start", flexWrap: "wrap" }}>
			{items.map((t, i) => (<span key={i} style={pillStyle}>{t}</span>))}
		</div>
	);
}

export function VacancyHero({ title, sub, pills = [] }: { title: string; sub?: string; pills?: string[] }) {
	return (
		<section style={{ padding: "140px 24px 60px", background: "#0A0A0A", textAlign: "center" }}>
			<div style={{ color: "var(--accent)", fontSize: "0.85rem", letterSpacing: 2, textTransform: "uppercase", marginBottom: 12 }}>Карьера в KONNEKTEAM</div>
			<h1 style={{ fontSize: "clamp(1.8rem, 4vw, 2.8rem)", fontWeight: 800, marginBottom: 20, lineHeight: 1.2 }}>{title}</h1>
			{sub ? <p style={{ color: "rgba(255,255,255,0.6)", maxWidth: 560, margin: "0 auto 32px", lineHeight: 1.7 }}>{sub}</p> : null}
			<Pills items={pills} />
		</section>
	);
}

export function VacancyNote({ children }: { children: ReactNode }) {
	return (
		<section style={{ padding: "0 24px 100px", background: "#0A0A0A" }}>
			<p style={{ maxWidth: 720, margin: "0 auto", color: "rgba(255,255,255,0.6)", textAlign: "center", lineHeight: 1.7 }}>{children}</p>
		</section>
	);
}

export function VacancyInfo({ v }: { v: Vacancy }) {
	const sections: [string, string[]][] = [["Обязанности", v.duties], ["Требования", v.requirements], ["Условия", v.conditions]];
	const paras = v.description.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
	return (
		<section style={{ padding: "0 24px 40px", background: "#0A0A0A" }}>
			<div style={{ maxWidth: 720, margin: "0 auto" }}>
				{paras.map((p, i) => (<p key={i} style={{ color: textColor, lineHeight: 1.75, marginBottom: 16 }}>{p}</p>))}
				{sections.filter(([, items]) => items.length > 0).map(([t, items]) => (
					<div key={t}>
						<h2 style={{ fontSize: "1.25rem", fontWeight: 700, margin: "32px 0 12px" }}>{t}</h2>
						<ul style={{ paddingLeft: 20, listStyle: "disc", color: textColor, lineHeight: 1.75 }}>
							{items.map((x, i) => (<li key={i} style={{ marginBottom: 6 }}>{x}</li>))}
						</ul>
					</div>
				))}
				{v.deadline ? <p style={{ color: "rgba(255,255,255,0.5)", marginTop: 24, fontSize: "0.9rem" }}>Принимаем анкеты до {formatDate(v.deadline)}</p> : null}
			</div>
		</section>
	);
}

export function VacancyCards({ list }: { list: VacancySummary[] }) {
	return (
		<section style={{ padding: "0 24px 100px", background: "#0A0A0A" }}>
			<div style={{ maxWidth: 1000, margin: "0 auto" }}>
				{list.length === 0 ? (
					<p style={{ color: "rgba(255,255,255,0.6)", textAlign: "center", lineHeight: 1.7 }}>
						Сейчас открытых вакансий нет. Напишите нам на <a href="mailto:sales@konnekteam.kz" style={{ color: "var(--accent)" }}>sales@konnekteam.kz</a>, если хотите работать в KONNEKTEAM.
					</p>
				) : (
					<div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(min(300px, 100%), 1fr))", gap: 24 }}>
						{list.map((v) => (
							<Link key={v.slug} href={`/vacancy/${v.slug}`} className="card" style={{ textDecoration: "none", display: "block" }}>
								<h2 style={{ fontSize: "1.2rem", fontWeight: 700, color: "#fff", marginBottom: 8 }}>{v.name}</h2>
								{vacancyMeta(v) ? <p style={{ color: "rgba(255,255,255,0.55)", fontSize: "0.9rem", marginBottom: 16 }}>{vacancyMeta(v)}</p> : null}
								<Pills items={vacancyPills(v)} center={false} />
								<div style={{ marginTop: 20, color: "var(--accent)", fontWeight: 600, fontSize: "0.9rem" }}>Подробнее и анкета →</div>
							</Link>
						))}
					</div>
				)}
			</div>
		</section>
	);
}
