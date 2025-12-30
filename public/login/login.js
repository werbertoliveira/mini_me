// public/login/login.js
(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  function setMsg(text) {
    const el = $("msg");
    if (el) el.textContent = text || "";
  }

  function projectBase() {
    // retorna /mini_me
    const parts = window.location.pathname.split("/").filter(Boolean);
    const idx = parts.indexOf("public");
    if (idx === -1) return "";
    return "/" + parts.slice(0, idx).join("/");
  }

  async function login(usuario, senha) {
    setMsg("");

    try {
      const res = await fetch(`${projectBase()}/api/auth/login.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include", // ⭐ ESSENCIAL (cookie de sessão)
        body: JSON.stringify({ usuario, senha }),
      });

      const data = await res.json();

      if (!data.sucesso) {
        setMsg(data.mensagem || "Falha no login.");
        return;
      }

      MiniMe.toast("Login realizado com sucesso!");
      window.location.href = "../dashboard/dashboard.html";

    } catch (err) {
      console.error(err);
      setMsg("Erro ao conectar com o servidor.");
    }
  }

  $("btn_login").addEventListener("click", () => {
    const usuario = $("usuario").value.trim();
    const senha = $("senha").value;

    if (!usuario || !senha) {
      setMsg("Informe usuário e senha.");
      return;
    }

    login(usuario, senha);
  });

  $("btn_demo").addEventListener("click", () => {
    MiniMe.toast("Modo demonstração");
    window.location.href = "../dashboard/dashboard.html";
  });
})();
