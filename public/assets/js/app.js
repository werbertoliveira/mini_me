// Helpers globais simples (padrão do projeto)
window.MiniMe = window.MiniMe || {};

MiniMe.toast = function(msg){
  const el = document.createElement("div");
  el.textContent = msg;
  el.style.position = "fixed";
  el.style.bottom = "18px";
  el.style.left = "50%";
  el.style.transform = "translateX(-50%)";
  el.style.padding = "10px 14px";
  el.style.borderRadius = "999px";
  el.style.background = "rgba(58,58,58,.92)";
  el.style.color = "#fff";
  el.style.fontWeight = "700";
  el.style.zIndex = "9999";
  document.body.appendChild(el);
  setTimeout(()=> el.remove(), 2400);
};
