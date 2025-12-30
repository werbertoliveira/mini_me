// public/dashboard/dashboard.js
(() => {
  "use strict";

  const moneyBR = (n) =>
    (Number(n) || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

  async function boot() {
    // 🔒 Proteção: usa o auth.js novo (MiniMeAuth.me + goLogin)
    const auth = window.MiniMeAuth;
    if (!auth || typeof auth.me !== "function") {
      console.warn("auth.js não carregou. Verifique <script src='../assets/js/auth.js'>");
      return;
    }

    const user = await auth.me();
    if (!user) {
      auth.goLogin();
      return;
    }

    // MVP visual: números fixos por enquanto (depois vira API do caixa)
    const vendas = 0;
    const saidas = 0;
    const saldo = vendas - saidas;

    document.getElementById("kpi_vendas").textContent = moneyBR(vendas);
    document.getElementById("kpi_saidas").textContent = moneyBR(saidas);
    document.getElementById("kpi_saldo").textContent = moneyBR(saldo);

    // atalhos data-go
    document.querySelectorAll("[data-go]").forEach((btn) => {
      btn.addEventListener("click", () => (window.location.href = btn.getAttribute("data-go")));
    });

    // ✅ Importar NF-e: agora é /xml (sem duplicidade)
    const btnImportar = document.getElementById("btn_importar");
    if (btnImportar) {
      btnImportar.addEventListener("click", () => {
        window.location.href = "../xml/importar.html";
      });
    }

    const btnPdv = document.getElementById("btn_pdv");
    if (btnPdv) {
      btnPdv.addEventListener("click", () => {
        window.location.href = "../pdv/caixa.html";
      });
    }

    // Logout
    const btnSair = document.getElementById("btn_sair");
    if (btnSair) {
      btnSair.addEventListener("click", async () => {
        try {
          await fetch(`${auth.base}/api/auth/logout.php`, { credentials: "include" });
        } catch (e) {}
        window.location.href = `${auth.base}/public/login/login.html`;
      });
    }

    if (window.MiniMe?.toast) MiniMe.toast(`Olá, ${user.nome}!`);
  }

  boot();
})();
