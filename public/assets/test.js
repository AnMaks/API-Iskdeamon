
function setStatus(text, ok = true) {
    const el = document.getElementById("status");
    el.textContent = (ok ? "OK " : "NO ") + text;
}

function print(obj) {
    document.getElementById("out").textContent = JSON.stringify(obj, null, 2);
}

async function doFetch(url, opts = {}) {
    const res = await fetch(url, opts);
    let data = null;

    try {
        data = await res.json();
    } catch (e) {
        const raw = await res.text();
        data = { ok: false, error: "Ответ не JSON", raw };
    }

    return { http: res.status, body: data };
}

async function health() {
    const url = "/api/health";
    setStatus("GET " + url);

    const r = await doFetch(url);
    print({ http: r.http, ...r.body });

    setStatus("Проверка API (HTTP " + r.http + ")", r.http < 400);
}

async function addToIndex() {
    const input = document.getElementById("fileAdd");
    if (!input.files.length) return setStatus("Выбери файл", false);

    const url = "/api/images";
    const fd = new FormData();
    fd.append("image", input.files[0]);

    setStatus("POST " + url);
    const r = await doFetch(url, { method: "POST", body: fd });
    print({ http: r.http, ...r.body });

    setStatus("Get (HTTP " + r.http + ")", r.http < 400);
}

async function searchUpload() {
    const input = document.getElementById("fileSearch");
    if (!input.files.length) return setStatus("Выбери файл", false);

    const count = Number(document.getElementById("countSearch").value || 10);
    const url = "/api/search?count=" + encodeURIComponent(count);

    const fd = new FormData();
    fd.append("image", input.files[0]);

    setStatus("POST " + url);
    const r = await doFetch(url, { method: "POST", body: fd });
    print({ http: r.http, ...r.body });

    setStatus("Поиск завершен (HTTP " + r.http + ")", r.http < 400);
}

async function searchById() {
    const id = Number(document.getElementById("imageId").value || 0);
    if (!id) return setStatus("Неверный ID", false);

    const count = Number(document.getElementById("countId").value || 10);
    const url = `/api/images/${id}/matches?count=${encodeURIComponent(count)}`;


    setStatus("GET " + url);
    const r = await doFetch(url);
    print({ http: r.http, ...r.body });

    setStatus("Поиск по id завершен (HTTP " + r.http + ")", r.http < 400);
}

async function deleteById() {
    const id = Number(document.getElementById("deleteId").value || 0);
    if (!id) return setStatus("Неверный ID", false);

    const url = `/api/images/${id}`;

    setStatus("DELETE " + url);
    const r = await doFetch(url, { method: "DELETE" });
    print({ http: r.http, ...r.body });

    setStatus("Удаление (HTTP " + r.http + ")", r.http < 400);
}

function clearOut() {
    print({});
    setStatus("Очищено");
}

// кнопки
document.getElementById("btnHealth").addEventListener("click", health);
document.getElementById("btnAdd").addEventListener("click", addToIndex);
document.getElementById("btnSearch").addEventListener("click", searchUpload);
document.getElementById("btnSearchId").addEventListener("click", searchById);
document.getElementById("btnDelete").addEventListener("click", deleteById);
document.getElementById("btnClear").addEventListener("click", clearOut);
