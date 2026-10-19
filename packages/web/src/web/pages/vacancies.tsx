import { useEffect, useState } from "react";
import { SEO, PageLayout } from "../components/layout";
import { isServer, SERVER_SLOT, bootData, loadVacancies, type VacancySummary } from "../lib/vacancies";
import { VacancyHero, VacancyCards, VacancyNote } from "../components/vacancy-blocks";

export default function VacanciesPage() {
	const [list, setList] = useState<VacancySummary[] | undefined>(() => (isServer ? undefined : bootData().list));
	const [failed, setFailed] = useState(false);
	useEffect(() => {
		if (list) return;
		let alive = true;
		loadVacancies().then((l) => { if (alive) setList(l); }).catch(() => { if (alive) setFailed(true); });
		return () => { alive = false; };
	}, []);

	return (
		<PageLayout>
			<SEO
				title="Вакансии"
				description="Открытые вакансии KONNEKTEAM в Уральске. Выберите вакансию и заполните анкету на сайте."
				keywords="вакансии Уральск, работа в IT Уральск, KONNEKTEAM вакансии"
				canonical="https://konnekteam.kz/vacancy"
			/>
			{isServer ? (
				<div dangerouslySetInnerHTML={{ __html: SERVER_SLOT }} />
			) : (
				<>
					<VacancyHero title="Вакансии" sub="Выберите вакансию и заполните анкету. Ответим в течение 3 рабочих дней." />
					{failed ? <VacancyNote>Не удалось загрузить вакансии. Обновите страницу.</VacancyNote> : list ? <VacancyCards list={list} /> : <div style={{ minHeight: 200 }} />}
				</>
			)}
		</PageLayout>
	);
}
