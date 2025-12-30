// public/nfe/importar.js
(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  // Ajuste aqui se seu backend estiver em outro caminho:
  const API_UPLOAD = "../../api/nfe/upload.php";
  const API_LISTA  = "../../api/nfe/listar_pendentes.php";

  const moneyBR = (n) =>
    (Number(n) || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

  function setMsg(text, kind = "muted") {
    const el = $("msg");
    if (!el) return;
    el.className = `small ${kind}`;
    el.textContent = text || "";
  }

  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function renderTabela(rows) {
    const tbody = $("tbl")?.querySelector("tbody");
    if (!tbody) return;

    if (!Array.isArray(rows) || rows.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="small">Nenhuma NF-e pendente.</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = rows
      .map((r) => {
        const id = escapeHtml(r.id);
        const emissao = escapeHtml(r.dt_emissao || r.emissao || "");
        const fornecedor = escapeHtml(r.fornecedor || r.nome_fornecedor || "");
        const total = moneyBR(r.total_nota ?? r.total ?? 0);
        const status = escapeHtml(r.status || "PENDENTE_MAPEAMENTO");

        // botão futuro (opcional) para abrir tela de mapeamento
        const btn = `<button class="btn btn-outline btn-sm" data-id="${id}">Abrir</button>`;

        return `
          <tr>
            <td>${id}</td>
            <td>${emissao}</td>
            <td>${fornecedor}</td>
            <td>${total}</td>
            <td style="display:flex;gap:8px;align-items:center;justify-content:space-between;">
              <span>${status}</span>
              ${btn}
            </td>
          </tr>
        `;
      })
      .join("");

    // Evento do botão Abrir (deixa pronto pro próximo módulo)
    tbody.querySelectorAll("button[data-id]").forEach((b) => {
      b.addEventListener("click", () => {
        const id = b.getAttribute("data-id");
        // Próxima etapa: tela de mapeamento
        // window.location.href = `./mapear.html?id=${encodeURIComponent(id)}`;
        alert("NF selecionada (mapear): " + id);
      });
    });
  }

  async function carregarPendentes() {
    const tbody = $("tbl")?.querySelector("tbody");
    if (tbody) {
      tbody.innerHTML = `
        <tr><td colspan="5" class="small">Carregando...</td></tr>
      `;
    }

    try {
      const res = await fetch(API_LISTA, {
        method: "GET",
        credentials: "include", // importante pra sessão PHP
        headers: { "Accept": "application/json" },
      });

      const data = await res.json().catch(() => null);

      if (!res.ok) {
        const msg = (data && (data.mensagem || data.erro)) || `Erro HTTP ${res.status}`;
        renderTabela([]);
        setMsg(msg, "danger");
        return;
      }

      // Formato esperado:
      // { sucesso:true, itens:[...] }
      // ou { ok:true, itens:[...] }
      const itens = data?.itens || data?.rows || data?.data || [];
      renderTabela(itens);

    } catch (e) {
      renderTabela([]);
      setMsg("Falha ao carregar pendências: " + (e?.message || e), "danger");
    }
  }

  async function enviarXML() {
    const file = $("xmlFile")?.files?.[0];
    if (!file) {
      setMsg("Selecione um arquivo .xml para enviar.", "warn");
      return;
    }

    if (!file.name.toLowerCase().endsWith(".xml")) {
      setMsg("O arquivo precisa ser .xml.", "warn");
      return;
    }

    const fd = new FormData();
    fd.append("xml", file);

    $("btnEnviar").disabled = true;
    setMsg("Enviando XML...", "muted");

    try {
      const res = await fetch(API_UPLOAD, {
        method: "POST",
        body: fd,
        credentials: "include", // importante pra sessão PHP
      });

      const data = await res.json().catch(() => null);

      if (!res.ok) {
        const msg = (data && (data.mensagem || data.erro)) || `Erro HTTP ${res.status}`;
        setMsg(msg, "danger");
        return;
      }

      // Formato recomendado:
      // { sucesso:true, mensagem:"...", nfe_id: 123 }
      if (data?.sucesso === false || data?.ok === false) {
        setMsg(data?.mensagem || data?.erro || "Falha ao importar.", "danger");
        return;
      }

      setMsg(data?.mensagem || "XML importado com sucesso!", "success");

      // limpa input e recarrega tabela
      $("xmlFile").value = "";
      await carregarPendentes();

    } catch (e) {
      setMsg("Erro ao enviar XML: " + (e?.message || e), "danger");
    } finally {
      $("btnEnviar").disabled = false;
    }
  }

  function bind() {
    $("btnEnviar")?.addEventListener("click", enviarXML);

    // Enter no input não envia por padrão em file, mas deixo o handler pronto
    $("xmlFile")?.addEventListener("change", () => setMsg(""));
  }

  // boot
  bind();
  carregarPendentes();
})();
