// Уведомление в Telegram (РОП и директор) через api/notify.php на hoster.kz.
// Токен бота хранится на сервере вне сайта и в репозиторий не попадает.
// Ошибка уведомления никогда не мешает отправке заявки.
export function notifySite(title: string, text: string): void {
  if (typeof window === "undefined") return;
  try {
    const body = JSON.stringify({ title, text, page: window.location.pathname });
    void fetch("/api/notify.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: body.length < 60000,
    }).catch(() => {});
  } catch {
    /* ничего */
  }
}
