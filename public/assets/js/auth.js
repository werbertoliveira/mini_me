(() => {
  "use strict";

  // Descobre o "base" do projeto mesmo estando em /public/*
  // Ex: /mini_me/public/dashboard/dashboard.html -> base = /mini_me
  function projectBase() {
    const parts = window.location.pathname.split("/").filter(Boolean);
    const idx = parts.indexOf("public");
    if (idx <= 0) return "";
    return "/" + parts.slice(0, idx).join("/");
  }

  const BASE = projectBase();
  const URL_ME = `${BASE}/api/auth/me.php`;

  async function checkAuth() {
    try {
      const res = await fetch(URL_ME, { credentials: "include" });
      if (res.status === 401) return null;

      const j = await res.json();
      return j && j.sucesso ? j.user : null;
    } catch (e) {
      // Se der erro de rede, evita loop infinito.
      console.warn("auth.js: falha ao validar sessão", e);
      return null;
    }
  }

  function goLogin() {
    window.location.href = `${BASE}/public/login/login.html`;
  }

  // Expor user global (opcional)
  window.MiniMeAuth = {
    base: BASE,
    me: checkAuth,
    goLogin,
  };

  // Protege todas as páginas (exceto as do /public/login/)
  const isLoginPage = window.location.pathname.includes("/public/login/");
  if (!isLoginPage) {
    checkAuth().then((user) => {
      if (!user) goLogin();
      else window.__USER__ = user;
    });
  }
})();
