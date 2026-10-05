/* GIRO — comportamento de tela (tema, prévias de cálculo, confirmação) */
(function () {
  'use strict';

  // ---------- Tema ----------
  var CHAVE = 'giro_tema';

  function aplicarTema(tema) {
    document.body.setAttribute('data-tema', tema);
    document.querySelectorAll('[data-tema-label]').forEach(function (el) {
      el.textContent = tema === 'claro' ? 'Modo escuro' : 'Modo claro';
    });
  }

  function temaAtual() {
    try {
      return localStorage.getItem(CHAVE) === 'claro' ? 'claro' : 'escuro';
    } catch (e) {
      return 'escuro';
    }
  }

  aplicarTema(temaAtual());

  document.addEventListener('click', function (ev) {
    var botao = ev.target.closest('[data-tema-toggle]');
    if (!botao) return;
    var novo = temaAtual() === 'claro' ? 'escuro' : 'claro';
    try {
      localStorage.setItem(CHAVE, novo);
    } catch (e) { /* modo privado: só não persiste */ }
    aplicarTema(novo);
  });

  // ---------- Números em pt-BR ----------
  function valor(id) {
    var el = document.getElementById(id);
    if (!el) return 0;
    var s = String(el.value).trim().replace(/\./g, '').replace(',', '.');
    var n = parseFloat(s);
    return isFinite(n) ? n : 0;
  }

  function brl(v) {
    return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // ---------- Prévia do total do dia (lançamento detalhado) ----------
  var previaTotal = document.getElementById('previa-total');
  if (previaTotal) {
    var campos = ['valor_base', 'promos', 'gorjetas', 'adicional', 'outros'];
    var recalcular = function () {
      var soma = campos.reduce(function (acc, id) { return acc + valor(id); }, 0);
      previaTotal.textContent = brl(soma);
    };
    campos.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', recalcular);
    });
    recalcular();
  }

  // ---------- Prévia dos km rodados ----------
  var previaKm = document.getElementById('previa-km');
  if (previaKm) {
    var recalcularKm = function () {
      var rodados = Math.max(0, Math.round(valor('km_final') - valor('km_inicial')));
      previaKm.textContent = rodados.toLocaleString('pt-BR') + ' km';
    };
    ['km_inicial', 'km_final'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', recalcularKm);
    });
    recalcularKm();
  }

  // ---------- Confirmação de exclusão ----------
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.hasAttribute('data-confirmar')) return;
    if (!window.confirm(form.getAttribute('data-confirmar') || 'Confirma a exclusão?')) {
      ev.preventDefault();
    }
  });
})();
