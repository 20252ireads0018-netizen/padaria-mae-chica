// Mãe Chica — interações leves (sem build)
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const num = (v) => {
    v = String(v ?? '').trim();
    if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
    return parseFloat(v) || 0;
  };
  const brl = (v) => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  window.MC = { num, brl };

  // Menu lateral no celular
  $('[data-menu]')?.addEventListener('click', () => document.body.classList.toggle('nav-open'));
  document.addEventListener('click', (e) => {
    if (document.body.classList.contains('nav-open') && !e.target.closest('.side, [data-menu]')) document.body.classList.remove('nav-open');
  });

  // Confirmação em ações destrutivas
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });

  // Diálogos: <button data-open="id">
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); document.getElementById(o.dataset.open)?.showModal(); }
    const c = e.target.closest('[data-close]');
    if (c) { e.preventDefault(); c.closest('dialog')?.close(); }
  });

  // Auto-envio de filtros
  $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

  // Editor de itens (encomendas, escolas, compras)
  $$('[data-items]').forEach((box) => {
    const tpl = $('template', box);
    const list = $('.rows', box);
    const priceKey = box.dataset.price || 'price';
    const recalc = () => {
      let total = 0;
      $$('.it.r', list).forEach((row) => {
        const q = num($('[data-q]', row).value);
        const p = num($('[data-p]', row).value);
        const s = q * p;
        total += s;
        $('.sub', row).textContent = brl(s);
      });
      $$('[data-total]', box.closest('form')).forEach((t) => (t.textContent = brl(total)));
      box.closest('form')?.dispatchEvent(new CustomEvent('items:total', { detail: total }));
    };
    const bind = (row) => {
      const sel = $('select', row);
      sel?.addEventListener('change', () => {
        const opt = sel.selectedOptions[0];
        if (opt && opt.value) {
          const d = $('[data-d]', row);
          if (!d.value) d.value = opt.textContent.trim();
          const p = $('[data-p]', row);
          p.value = (parseFloat(opt.dataset[priceKey] || 0)).toFixed(2).replace('.', ',');
        }
        recalc();
      });
      $$('input', row).forEach((i) => i.addEventListener('input', recalc));
      $('[data-del]', row)?.addEventListener('click', () => { row.remove(); renumber(); recalc(); });
    };
    let idx = $$('.it.r', list).length;
    const renumber = () => $$('.it.r', list).forEach((row, i) => $$('[name]', row).forEach((el) => (el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`))));
    $('[data-add]', box)?.addEventListener('click', () => {
      const html = tpl.innerHTML.replace(/__i__/g, idx++);
      list.insertAdjacentHTML('beforeend', html);
      const row = list.lastElementChild;
      bind(row);
      renumber();
      $('select', row)?.focus();
    });
    $$('.it.r', list).forEach(bind);
    recalc();
  });

  // Fechamento: diferença da contagem em tempo real
  const counted = $('[data-counted]');
  if (counted) {
    const expected = parseFloat(counted.dataset.expected);
    const out = $('[data-diff]');
    const upd = () => {
      if (!counted.value.trim()) { out.textContent = '—'; out.className = ''; return; }
      const d = num(counted.value) - expected;
      out.textContent = (d > 0 ? '+' : '') + brl(d);
      out.className = Math.abs(d) < 0.005 ? 'pos b' : d > 0 ? 'pos b' : 'neg b';
    };
    counted.addEventListener('input', upd);
    upd();
  }

  // Lançamento: categorias conforme o tipo
  $$('[data-entry-form]').forEach((f) => {
    const type = $('[name=type]', f), cat = $('[name=category]', f);
    const cats = JSON.parse(f.dataset.categories);
    const fill = () => { const v = cat.value; cat.innerHTML = cats[type.value].map((c) => `<option ${c === v ? 'selected' : ''}>${c}</option>`).join(''); };
    type.addEventListener('change', fill); fill();
  });

  // Rodízio / seleção múltipla: marcar todos
  $$('[data-checkall]').forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault();
    const boxes = $$(`input[name="${b.dataset.checkall}"]`);
    const all = boxes.every((x) => x.checked);
    boxes.forEach((x) => (x.checked = !all));
  }));
})();
