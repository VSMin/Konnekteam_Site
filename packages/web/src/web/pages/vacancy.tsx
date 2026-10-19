import { useEffect, useState, type CSSProperties } from "react";
import { Link } from "wouter";
import { SEO, PageLayout } from "../components/layout";
import { isServer, SERVER_SLOT, bootData, loadVacancy, loadVacancies, type Vacancy, type VacancySummary } from "../lib/vacancies";
import { VacancyHero, VacancyInfo, VacancyNote, VacancyCards, vacancyMeta, vacancyPills } from "../components/vacancy-blocks";

// Страница одной вакансии. Текст, условия и вопросы анкеты задаются в EspoCRM (раздел «Вакансии»).
export default function VacancyPage({ params }: { params: { slug?: string } }) {
	const slug = (params.slug ?? "").toLowerCase();
	const [vac, setVac] = useState<Vacancy | null | undefined>(() => {
		if (isServer) return undefined;
		const pre = bootData().vacancy;
		return pre && pre.slug === slug ? pre : undefined;
	});
	const [failed, setFailed] = useState(false);
	useEffect(() => {
		if (vac && vac.slug === slug) return;
		let alive = true;
		setVac(undefined);
		setFailed(false);
		loadVacancy(slug).then((v) => { if (alive) setVac(v); }).catch(() => { if (alive) setFailed(true); });
		return () => { alive = false; };
	}, [slug]);

	return (
		<PageLayout>
			<SEO
				title={vac ? `Вакансия: ${vac.name}` : "Вакансия"}
				description={vac ? describe(vac) : "Вакансия KONNEKTEAM в Уральске."}
				keywords="вакансия Уральск, работа KONNEKTEAM"
				canonical={`https://konnekteam.kz/vacancy/${slug}`}
			/>
			{isServer ? (
				<div dangerouslySetInnerHTML={{ __html: SERVER_SLOT }} />
			) : failed ? (
				<><VacancyHero title="Вакансия" /><VacancyNote>Не удалось загрузить вакансию. Обновите страницу.</VacancyNote></>
			) : vac === undefined ? (
				<VacancyHero title="Загрузка…" />
			) : vac === null ? (
				<ClosedVacancy />
			) : (
				<>
					<VacancyHero title={vac.name} sub={vacancyMeta(vac)} pills={vacancyPills(vac)} />
					<VacancyInfo v={vac} />
					<VacancyForm key={vac.slug} vac={vac} />
				</>
			)}
		</PageLayout>
	);
}

function describe(v: Vacancy) {
	const first = v.description.split(/\r?\n/).map((s) => s.trim()).find(Boolean);
	return (first || `${v.name} в KONNEKTEAM. Заполните анкету на сайте.`).slice(0, 160);
}

function ClosedVacancy() {
	const [list, setList] = useState<VacancySummary[] | undefined>(() => bootData().list);
	useEffect(() => {
		if (!list) loadVacancies().then(setList).catch(() => setList([]));
	}, []);
	return (
		<>
			<VacancyHero title="Вакансия закрыта" sub="Эта вакансия закрыта или не найдена. Посмотрите открытые вакансии:" />
			{list ? <VacancyCards list={list} /> : null}
		</>
	);
}

type Step = "info" | "questionnaire" | "success";

const normalizePhone = (p: string) => p.replace(/[\s\-()+]/g, "");
const isPhone = (p: string) => /^(7|8)\d{10}$/.test(normalizePhone(p));
function isHhResume(url: string) {
	try { const u = new URL(url); return /^(www\.)?hh\.kz$/.test(u.hostname) && u.pathname.includes("resume"); } catch { return false; }
}
function isUrl(url: string) {
	try { return /^https?:$/.test(new URL(url).protocol); } catch { return false; }
}

const inputStyle: CSSProperties = {
	width: "100%", background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.15)", borderRadius: 10,
	padding: "14px 16px", color: "#fff", fontSize: "1rem", outline: "none", boxSizing: "border-box", transition: "border-color 0.2s", fontFamily: "inherit",
};
const errorStyle: CSSProperties = { color: "#ff6b6b", fontSize: "0.82rem", marginTop: 6 };
const labelStyle: CSSProperties = { display: "block", marginBottom: 8, fontSize: "0.9rem", color: "rgba(255,255,255,0.7)" };
const hintStyle: CSSProperties = { marginTop: 6, fontSize: "0.78rem", color: "rgba(255,255,255,0.35)" };
const primaryBtn: CSSProperties = { padding: "14px 32px", border: "none", cursor: "pointer", fontSize: "1rem", fontWeight: 700 };
const ghostBtn: CSSProperties = { padding: "14px 24px", background: "rgba(255,255,255,0.07)", border: "1px solid rgba(255,255,255,0.15)", borderRadius: 10, color: "#fff", cursor: "pointer", fontSize: "0.95rem" };
const bad = (on: boolean): CSSProperties => ({ ...inputStyle, borderColor: on ? "#ff6b6b" : "rgba(255,255,255,0.15)" });

function VacancyForm({ vac }: { vac: Vacancy }) {
	const qs = vac.questions;
	const [step, setStep] = useState<Step>("info");
	const [current, setCurrent] = useState(0);
	const [sending, setSending] = useState(false);
	const [errors, setErrors] = useState<Record<string, string>>({});
	const [info, setInfo] = useState({ name: "", phone: "", resume: "" });
	// Антиспам: боты заполняют скрытое поле и отправляют анкету мгновенно (проверяет и сервер).
	const [hp, setHp] = useState("");
	const [startedAt] = useState(() => Date.now());
	const [answers, setAnswers] = useState<string[]>(() => qs.map(() => ""));

	const toForm = () => {
		const el = document.getElementById("anketa");
		window.scrollTo({ top: el ? el.getBoundingClientRect().top + window.scrollY - 90 : 0, behavior: "smooth" });
	};

	function validateInfo() {
		const e: Record<string, string> = {};
		if (info.name.trim().split(/\s+/).length < 2) e.name = "Введите полное ФИО (минимум два слова)";
		if (!isPhone(info.phone)) e.phone = "Введите корректный казахстанский номер";
		const r = info.resume.trim();
		if (vac.requireHh ? !isHhResume(r) : r !== "" && !isUrl(r))
			e.resume = vac.requireHh ? "Ссылка должна быть на резюме hh.kz (например: https://hh.kz/resume/...)" : "Проверьте ссылку: она должна начинаться с https://";
		setErrors(e);
		return Object.keys(e).length === 0;
	}

	function answerOk(i: number) {
		const min = qs[i].minLength;
		if (answers[i].trim().length < min) {
			setErrors({ answer: `Пожалуйста, дайте развёрнутый ответ (минимум ${min} символов)` });
			return false;
		}
		setErrors({});
		return true;
	}

	function onInfoNext() {
		if (!validateInfo()) return;
		if (qs.length) { setStep("questionnaire"); setCurrent(0); toForm(); } else void submit();
	}
	function onNext() { if (answerOk(current)) { setCurrent((c) => c + 1); toForm(); } }
	function onPrev() {
		setErrors({});
		if (current > 0) setCurrent((c) => c - 1); else setStep("info");
		toForm();
	}

	async function submit() {
		if (qs.length && !answerOk(current)) return;
		setSending(true);
		try {
			const res = await fetch("/api/candidate.php", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ slug: vac.slug, name: info.name.trim(), phone: normalizePhone(info.phone), resume: info.resume.trim(), answers, hp, elapsed: Date.now() - startedAt }),
			});
			const data = (await res.json().catch(() => ({}))) as { ok?: boolean; error?: string };
			if (res.ok && data.ok) { setStep("success"); toForm(); }
			else alert(data.error || "Ошибка отправки. Попробуйте ещё раз или позвоните нам.");
		} catch {
			alert("Нет соединения. Проверьте интернет и попробуйте снова.");
		} finally {
			setSending(false);
		}
	}

	const q = qs[current];
	const progress = step === "info" ? 2 : ((current + 1) / Math.max(qs.length, 1)) * 100;
	const parts = info.name.trim().split(/\s+/);

	return (
		<section id="anketa" style={{ padding: "20px 24px 100px", background: "#0A0A0A" }}>
			<div style={{ maxWidth: 720, margin: "0 auto" }}>
				{step !== "success" && (
					<div style={{ marginBottom: 40 }}>
						<h2 style={{ fontSize: "1.4rem", fontWeight: 700, marginBottom: 24 }}>Анкета кандидата</h2>
						<div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.8rem", color: "rgba(255,255,255,0.4)", marginBottom: 8 }}>
							<span>{step === "info" ? (qs.length ? "Шаг 1 из 2 — Ваши данные" : "Ваши данные") : `Шаг 2 из 2 — Вопрос ${current + 1} из ${qs.length}`}</span>
							<span>{step === "info" ? "0%" : `${Math.round(progress)}%`}</span>
						</div>
						<div style={{ height: 4, background: "rgba(255,255,255,0.1)", borderRadius: 2, overflow: "hidden" }}>
							<div style={{ height: "100%", width: `${progress}%`, background: "var(--accent)", borderRadius: 2, transition: "width 0.4s ease" }} />
						</div>
					</div>
				)}

				{step === "info" && (
					<div style={{ display: "flex", flexDirection: "column", gap: 20, position: "relative" }}>
						<input type="text" name="website" tabIndex={-1} autoComplete="off" aria-hidden="true" value={hp} onChange={(e) => setHp(e.target.value)}
							style={{ position: "absolute", left: "-9999px", width: "1px", height: "1px", opacity: 0 }} />
						<div>
							<label style={labelStyle}>ФИО *</label>
							<input style={bad(!!errors.name)} placeholder="Иванов Иван Иванович" value={info.name} onChange={(e) => setInfo((p) => ({ ...p, name: e.target.value }))} />
							{errors.name && <div style={errorStyle}>{errors.name}</div>}
						</div>
						<div>
							<label style={labelStyle}>Телефон *</label>
							<input style={bad(!!errors.phone)} placeholder="+7 700 000 00 00" type="tel" value={info.phone} onChange={(e) => setInfo((p) => ({ ...p, phone: e.target.value }))} />
							{errors.phone && <div style={errorStyle}>{errors.phone}</div>}
						</div>
						<div>
							<label style={labelStyle}>{vac.requireHh ? "Ссылка на резюме на hh.kz *" : "Ссылка на резюме (необязательно)"}</label>
							<input style={bad(!!errors.resume)} placeholder={vac.requireHh ? "https://hh.kz/resume/xxxxxxxxxxxxxxxx" : "https://"} type="url" value={info.resume} onChange={(e) => setInfo((p) => ({ ...p, resume: e.target.value }))} />
							{errors.resume && <div style={errorStyle}>{errors.resume}</div>}
							<div style={hintStyle}>Убедитесь, что резюме открывается по ссылке без авторизации</div>
						</div>
						<button className="btn-primary" onClick={onInfoNext} disabled={sending} style={{ ...primaryBtn, marginTop: 12, alignSelf: "flex-start", opacity: sending ? 0.6 : 1 }}>
							{qs.length ? "Далее — пройти анкету →" : sending ? "Отправка..." : "Отправить анкету"}
						</button>
					</div>
				)}

				{step === "questionnaire" && q && (
					<div>
						<div style={{ background: "rgba(227,30,36,0.05)", border: "1px solid rgba(227,30,36,0.2)", borderRadius: 14, padding: "28px 28px 32px" }}>
							{q.label ? <div style={{ color: "var(--accent)", fontSize: "0.8rem", letterSpacing: 1, textTransform: "uppercase", marginBottom: 16, fontWeight: 600 }}>{q.label}</div> : null}
							<p style={{ fontSize: "1.05rem", lineHeight: 1.75, color: "rgba(255,255,255,0.85)", marginBottom: 28, whiteSpace: "pre-line" }}>{q.question}</p>
							<textarea style={{ ...bad(!!errors.answer), minHeight: 180, resize: "vertical" }} placeholder="Ваш ответ…" value={answers[current]}
								onChange={(e) => { const v = e.target.value; setAnswers((a) => a.map((x, i) => (i === current ? v : x))); }} />
							{errors.answer && <div style={errorStyle}>{errors.answer}</div>}
							<div style={hintStyle}>{answers[current].length} символов</div>
						</div>
						<div style={{ display: "flex", gap: 12, marginTop: 24, justifyContent: "space-between", alignItems: "center", flexWrap: "wrap" }}>
							<button onClick={onPrev} style={ghostBtn}>← Назад</button>
							{current < qs.length - 1 ? (
								<button className="btn-primary" onClick={onNext} style={primaryBtn}>Следующий вопрос →</button>
							) : (
								<button className="btn-primary" onClick={() => void submit()} disabled={sending} style={{ ...primaryBtn, opacity: sending ? 0.6 : 1, cursor: sending ? "not-allowed" : "pointer" }}>
									{sending ? "Отправка..." : "Отправить анкету ✓"}
								</button>
							)}
						</div>
						<div style={{ display: "flex", gap: 6, justifyContent: "center", marginTop: 24, flexWrap: "wrap" }}>
							{qs.map((_, i) => (
								<div key={i} style={{ width: 8, height: 8, borderRadius: "50%", background: i < current ? "var(--accent)" : i === current ? "#fff" : "rgba(255,255,255,0.2)", transition: "background 0.3s" }} />
							))}
						</div>
					</div>
				)}

				{step === "success" && (
					<div style={{ textAlign: "center", paddingTop: 40 }}>
						<h2 style={{ fontSize: "1.8rem", fontWeight: 800, marginBottom: 16 }}>Анкета отправлена!</h2>
						<p style={{ color: "rgba(255,255,255,0.6)", lineHeight: 1.7, maxWidth: 480, margin: "0 auto 32px" }}>
							Мы получили вашу анкету на вакансию «{vac.name}» и свяжемся с вами по номеру <strong style={{ color: "#fff" }}>{info.phone}</strong> в течение 3 рабочих дней. Удачи, {parts[1] ?? parts[0]}!
						</p>
						<Link href="/vacancy" className="btn-primary" style={{ textDecoration: "none", display: "inline-block" }}>Все вакансии</Link>
					</div>
				)}
			</div>
		</section>
	);
}
