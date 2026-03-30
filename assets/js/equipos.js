/* ================================================
   assets/js/equipos.js
   Lógica CRUD completa para inventario de equipos
================================================ */

// ── Estado global ─────────────────────────────────────────────
let todosEquipos     = [];
let equiposFiltrados = [];
let todosUsuarios    = [];   // cache de dbo.USUARIOS_TPU
let sortCol          = 'antiguedad';
let sortAsc          = true;
let eliminarId       = null;
let toastTimer       = null;

const marcaIconos = {
  'HP':'🔵','DELL':'🔷','ASUS':'🟣','ACER':'🟢','Samsung':'🔶'
};

// ══ CARGA DE USUARIOS (USUARIOS_TPU) ══════════════════════════
async function cargarUsuarios() {
  try {
    const res  = await fetch('get_usuarios.php');
    const data = await res.json();
    if (data.ok) {
      todosUsuarios = data.usuarios || [];
    } else {
      console.warn('No se pudo cargar usuarios:', data.msg);
      todosUsuarios = [];
    }
  } catch (e) {
    console.warn('Error cargando usuarios:', e.message);
    todosUsuarios = [];
  }
}

// Puebla cualquier <select> con los usuarios cargados.
// loginActual: valor preseleccionado (para edición)
function poblarSelectUsuario(selectId, loginActual = '') {
  const sel = document.getElementById(selectId);
  if (!sel) return;

  // Limpiar opciones previas manteniendo solo la primera (placeholder)
  while (sel.options.length > 1) sel.remove(1);

  todosUsuarios.forEach(u => {
    const opt       = document.createElement('option');
    opt.value       = u.login;
    opt.textContent = u.nombre
      ? `${u.login} — ${u.nombre}`
      : u.login;
    if (u.login === loginActual) opt.selected = true;
    sel.appendChild(opt);
  });

  // Si el login actual no está en la lista (usuario eliminado de TPU),
  // agregarlo igual para no perder el dato existente
  if (loginActual && !todosUsuarios.find(u => u.login === loginActual)) {
    const opt       = document.createElement('option');
    opt.value       = loginActual;
    opt.textContent = `${loginActual} (no encontrado en directorio)`;
    opt.selected    = true;
    opt.style.color = '#a94442';
    sel.insertBefore(opt, sel.options[1]);
  }
}

// ══ READ ══════════════════════════════════════════════════════
async function cargarEquipos() {
  mostrarEstado('loading');
  try {
    const res  = await fetch('get_equipos.php');
    const data = await res.json();
    if (data.error) { mostrarEstado('error', data.error); return; }
    todosEquipos = data.equipos || [];
    poblarMarcas();
    poblarUbicacionesFiltro();
    aplicarFiltros();
    actualizarSubtitle();
    renderDashboardPrincipal();
  } catch (e) {
    mostrarEstado('error', 'No se pudo conectar con get_equipos.php: ' + e.message);
  }
}

function poblarMarcas() {
  const marcas = [...new Set(todosEquipos.map(e => (e.marca||'').trim()))].sort();
  const sel    = document.getElementById('selectMarca');
  while (sel.options.length > 1) sel.remove(1);
  marcas.forEach(m => {
    if (!m) return;
    const opt = document.createElement('option');
    opt.value = m; opt.textContent = m;
    sel.appendChild(opt);
  });
}

function poblarUbicacionesFiltro() {
  const sel = document.getElementById('selectUbicacion');
  if (!sel) return;
  const locs = [...new Set(todosEquipos.map(e => (e.ubicacion||'').trim()).filter(Boolean))].sort();
  while (sel.options.length > 1) sel.remove(1);
  locs.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u; opt.textContent = u;
    sel.appendChild(opt);
  });
}

// ══ DASHBOARD PRINCIPAL ═══════════════════════════════════════
function renderDashboardPrincipal() {
  const equipos  = todosEquipos;
  const total    = equipos.length;
  const activos  = equipos.filter(e => e.estado !== 'Dado de baja');
  const bajas    = equipos.filter(e => e.estado === 'Dado de baja');

  // KPIs de antigüedad sólo sobre activos
  const critical = activos.filter(e => parseInt(e.anios) >= 4).length;
  const warn     = activos.filter(e => parseInt(e.anios) === 3).length;
  const ok       = activos.filter(e => parseInt(e.anios) >= 1 && parseInt(e.anios) < 3).length;
  const nuevo    = activos.filter(e => parseInt(e.anios) === 0).length;

  // KPIs operativos por estado
  const disponibles = equipos.filter(e => e.estado === 'Disponible').length;
  const nuevos      = equipos.filter(e => e.estado === 'Nuevo').length;
  const asignados   = equipos.filter(e => e.estado === 'Asignado').length;
  const enServicio  = equipos.filter(e => e.estado === 'Servicio técnico' || e.estado === 'Servicio tecnico').length;

  animarNumero('kpiTotal',    activos.length);
  animarNumero('kpiCritical', critical);
  animarNumero('kpiWarn',     warn);
  animarNumero('kpiOk',       ok);
  animarNumero('kpiNew',      nuevo);

  const opEl = document.getElementById('kpiOperativos');
  if (opEl) {
    opEl.innerHTML = `
      <div class="kpi-op-card" onclick="filtrarPorEstado('Nuevo')">
        <div class="kpi-op-num" style="color:#8e44ad">${nuevos}</div>
        <div class="kpi-op-lbl">📦 Nuevos</div>
      </div>
      <div class="kpi-op-card" onclick="filtrarPorEstado('Disponible')">
        <div class="kpi-op-num" style="color:#057F79">${disponibles}</div>
        <div class="kpi-op-lbl">🟢 Disponibles</div>
      </div>
      <div class="kpi-op-card" onclick="filtrarPorEstado('Asignado')">
        <div class="kpi-op-num" style="color:#2980b9">${asignados}</div>
        <div class="kpi-op-lbl">🔵 Asignados</div>
      </div>
      <div class="kpi-op-card" onclick="filtrarPorEstado('Servicio técnico')">
        <div class="kpi-op-num" style="color:#ff9f43">${enServicio}</div>
        <div class="kpi-op-lbl">🟡 Servicio técnico</div>
      </div>
      <div class="kpi-op-card" onclick="filtrarPorEstado('Dado de baja')">
        <div class="kpi-op-num" style="color:#a94442">${bajas.length}</div>
        <div class="kpi-op-lbl">🔴 Dados de baja</div>
      </div>
    `;
  }

  // Ubicaciones (solo activos)
  const ubiMap = {};
  activos.forEach(e => {
    const u = (e.ubicacion||'').trim() || '(Sin ubicación)';
    if (!ubiMap[u]) ubiMap[u] = 0;
    ubiMap[u]++;
  });
  const ubiEntries = Object.entries(ubiMap).sort((a,b) => b[1]-a[1]);
  const maxUbi     = Math.max(...ubiEntries.map(x => x[1]), 1);

  const ubiIcons = {
    'Puerto Varas':   '🌲',
    'Santiago':       '🏢',
    'Puerto Natales': '🌬️',
    '(Sin ubicación)':'📦',
  };

  const ubiHTML = ubiEntries.map(([loc, cnt]) => {
    const pct     = Math.round(cnt / activos.length * 100);
    const fill    = Math.round(cnt / maxUbi * 100);
    const icon    = ubiIcons[loc] || '📍';
    const isNone  = loc === '(Sin ubicación)';
    const dispUbi = activos.filter(e => (e.ubicacion||'(Sin ubicación)') === loc && e.estado === 'Disponible').length;
    const nuevoUbi= activos.filter(e => (e.ubicacion||'(Sin ubicación)') === loc && e.estado === 'Nuevo').length;
    const servUbi = activos.filter(e => (e.ubicacion||'(Sin ubicación)') === loc && (e.estado === 'Servicio técnico' || e.estado === 'Servicio tecnico')).length;
    const dispTotal = dispUbi + nuevoUbi; // disponibles + nuevos = para asignar

    // Etiquetas secundarias
    const tags = [];
    if (servUbi)  tags.push(`<span class="ubi-tag ubi-tag-serv">${servUbi} en serv.</span>`);

    return `<div class="ubi-card${isNone?' ubi-card-none':''}" id="ubicard-${esc(loc)}" onclick="abrirDetalleUbi('${esc(loc)}')">
      <div class="ubi-card-header">
        <span class="ubi-card-name">${icon} ${esc(loc)}</span>
        <span class="ubi-card-total">${cnt} total</span>
      </div>
      <div class="ubi-card-disp-num${dispTotal === 0 ? ' ubi-disp-zero' : ''}">${dispTotal}</div>
      <div class="ubi-card-disp-lbl">disp. para asignar</div>
      ${tags.length ? `<div class="ubi-card-tags">${tags.join('')}</div>` : ''}
      <div class="ubi-card-bar"><div class="ubi-card-bar-fill" style="width:${fill}%"></div></div>
      <div class="ubi-card-cta">Ver detalle ▾</div>
    </div>`;
  }).join('');

  document.getElementById('ubicacionCards').innerHTML = ubiHTML ||
    '<p style="color:var(--sb-text-light);font-size:13px;">Sin datos de ubicación aún.</p>';

  // Renderizar top críticos y marcas (en función separada por legibilidad)
  renderTopCriticosYMarcas(activos);
}

// ── Detalle inline de ubicación ───────────────────────────────
function abrirDetalleUbi(loc) {
  const panel   = document.getElementById('ubiDetalle');
  const titulo  = document.getElementById('ubiDetalleTitulo');
  const body    = document.getElementById('ubiDetalleBody');

  // Toggle: cerrar si ya está abierta la misma
  if (panel.dataset.loc === loc && panel.style.display !== 'none') {
    cerrarDetalleUbi();
    return;
  }
  panel.dataset.loc = loc;

  // Marcar tarjeta activa
  document.querySelectorAll('.ubi-card').forEach(c => c.classList.remove('ubi-card-active'));
  const cardEl = document.getElementById(`ubicard-${loc}`);
  if (cardEl) cardEl.classList.add('ubi-card-active');

  const icon = { 'Puerto Varas':'🌊','Santiago':'🏙','Puerto Natales':'🏔','(Sin ubicación)':'📦' }[loc] || '📍';
  titulo.innerHTML = `${icon} <strong>${esc(loc)}</strong> — detalle por estado`;

  const equiposUbi = todosEquipos.filter(e => {
    const u = (e.ubicacion||'').trim() || '(Sin ubicación)';
    return u === loc;
  });

  // Agrupar por estado en el orden definido
  const ordenEstados = ['Disponible','Asignado','Servicio técnico','Dado de baja',''];
  const grupos = {};
  ordenEstados.forEach(s => { grupos[s] = []; });
  equiposUbi.forEach(e => {
    const s = (e.estado||'').trim();
    const key = grupos[s] !== undefined ? s : '';
    grupos[key].push(e);
  });

  const estadoConfig = {
    'Disponible':       { cls:'estado-disponible', emoji:'🟢', label:'Disponible' },
    'Asignado':         { cls:'estado-asignado',   emoji:'🔵', label:'Asignado' },
    'Servicio técnico': { cls:'estado-servicio',   emoji:'🟡', label:'Servicio técnico' },
    'Dado de baja':     { cls:'estado-baja',       emoji:'🔴', label:'Dado de baja' },
    '':                 { cls:'estado-otro',        emoji:'⚪', label:'Sin estado' },
  };

  let html = '';
  let hayDatos = false;

  ordenEstados.forEach(estado => {
    const lista = grupos[estado];
    if (!lista || lista.length === 0) return;
    hayDatos = true;
    const cfg = estadoConfig[estado] || estadoConfig[''];

    html += `
      <div class="ubi-grupo">
        <div class="ubi-grupo-header">
          <span class="estado-equipo-badge ${cfg.cls}">${cfg.emoji} ${cfg.label}</span>
          <span class="ubi-grupo-cnt">${lista.length} equipo${lista.length !== 1 ? 's' : ''}</span>
        </div>
        <table class="ubi-mini-table">
          <thead>
            <tr>
              <th>Etiqueta</th>
              <th>Usuario</th>
              <th>Marca / Modelo</th>
              <th>Antigüedad</th>
              ${estado === 'Dado de baja' ? '<th>Motivo</th>' : ''}
            </tr>
          </thead>
          <tbody>
            ${lista.map(e => {
              const anios = parseInt(e.anios) || 0;
              const badge = getBadge(anios);
              const esBaja = estado === 'Dado de baja';
              return `<tr class="${esBaja?'row-baja':''}">
                <td><span class="etiqueta-tag etiqueta-link" style="font-size:10px;cursor:pointer" title="Buscar en tabla" onclick="buscarEtiqueta('${esc(e.etiqueta||'')}')">${esc(e.etiqueta||'—')}</span></td>
                <td>${esc(e.usuario||'—')}</td>
                <td><span title="${esc(e.modelo||'')}">${esc((e.marca||'').trim())} <span style="color:var(--sb-text-light);font-size:11px">${esc(e.modelo||'')}</span></span></td>
                <td><span class="badge ${badge.cls}" style="font-size:10px;padding:2px 7px">${anios}a ${parseInt(e.meses)||0}m</span></td>
                ${esBaja ? `<td style="font-size:11px;color:var(--sb-text-light)">${esc(e.motivo_baja||'—')}</td>` : ''}
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>`;
  });

  body.innerHTML = hayDatos ? html : '<p style="padding:16px;color:var(--sb-text-light);font-size:13px;">Sin equipos registrados en esta ubicación.</p>';
  panel.style.display = 'block';

  // Scroll suave al panel
  setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
}

function cerrarDetalleUbi() {
  const panel = document.getElementById('ubiDetalle');
  panel.style.display = 'none';
  panel.dataset.loc = '';
  document.querySelectorAll('.ubi-card').forEach(c => c.classList.remove('ubi-card-active'));
}

function renderTopCriticosYMarcas(activos) {
  const sorted = [...activos]
    .filter(e => e.antiguedad)
    .sort((a,b) => {
      const da = parseInt(a.anios)*365 + parseInt(a.meses||0)*30 + parseInt(a.dias||0);
      const db = parseInt(b.anios)*365 + parseInt(b.meses||0)*30 + parseInt(b.dias||0);
      return db - da;
    })
    .slice(0, 5);

  const criticosHTML = sorted.map((e, i) => {
    const anios = parseInt(e.anios) || 0;
    const nivel = anios >= 4 ? 'nivel-4' : 'nivel-3';
    const estadoBadge = getEstadoBadge(e.estado);
    return `<div class="critico-item ${nivel}">
      <div class="critico-rank">${i + 1}</div>
      <div class="critico-info">
        <div class="critico-usuario" title="${esc(e.usuario||'')}">
          ${esc(e.etiqueta || '—')} · ${esc(e.usuario||'Sin usuario')}
        </div>
        <div class="critico-detalle">${esc((e.marca||'').trim())} ${esc(e.modelo||'')} · ${esc(e.ubicacion||'Sin ubicación')}</div>
        <div style="margin-top:3px">${estadoBadge}</div>
      </div>
      <div class="critico-age">
        <div class="critico-age-num">${anios}</div>
        <div class="critico-age-lbl">años</div>
      </div>
    </div>`;
  }).join('');

  document.getElementById('topCriticos').innerHTML = criticosHTML ||
    '<div style="text-align:center;padding:20px;color:var(--sb-text-light);font-size:13px;">Sin datos.</div>';

  // Barras de marcas (solo activos)
  const marcaMap = {};
  activos.forEach(e => {
    const m = (e.marca||'N/A').trim();
    marcaMap[m] = (marcaMap[m]||0) + 1;
  });
  const marcaEntries = Object.entries(marcaMap).sort((a,b) => b[1]-a[1]);
  const maxMarca = Math.max(...marcaEntries.map(x => x[1]), 1);
  const accentMarcas = ['HP','ASUS','ACER'];

  const marcasHTML = marcaEntries.map(([marca, cnt]) => {
    const pct = Math.round(cnt / maxMarca * 100);
    const isAccent = accentMarcas.includes(marca.toUpperCase());
    return `<div class="marca-bar-row">
      <span class="marca-bar-label">${esc(marca)}</span>
      <div class="marca-bar-track">
        <div class="marca-bar-fill${isAccent?' accent':''}" style="width:${pct}%"></div>
      </div>
      <span class="marca-bar-count">${cnt}</span>
    </div>`;
  }).join('');

  document.getElementById('marcasBars').innerHTML = marcasHTML;
}

function animarNumero(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  const start    = 0;
  const duration = 600;
  const startTs  = performance.now();
  const step = ts => {
    const progress = Math.min((ts - startTs) / duration, 1);
    const ease     = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(start + (target - start) * ease);
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target;
  };
  requestAnimationFrame(step);
}

function filtrarPorUbicacion(ubicacion) {
  const sel = document.getElementById('selectUbicacion');
  if (sel) sel.value = ubicacion;
  aplicarFiltros();
  document.querySelector('.section-divider').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function filtrarPorEstado(estado) {
  const sel = document.getElementById('selectEstado');
  if (sel) sel.value = estado;
  aplicarFiltros();
  document.querySelector('.section-divider').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function buscarEtiqueta(etiqueta) {
  if (!etiqueta) return;
  // Limpiar filtros previos y poner la etiqueta en el buscador
  document.getElementById('selectMarca').value    = '';
  document.getElementById('selectAntiedad').value = '';
  const ubSel = document.getElementById('selectUbicacion');
  if (ubSel) ubSel.value = '';
  const estSel = document.getElementById('selectEstado');
  if (estSel) estSel.value = '';
  document.getElementById('inputBuscar').value = etiqueta;
  aplicarFiltros();
  document.querySelector('.section-divider').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ══ CREATE ════════════════════════════════════════════════════
function abrirModalNuevo() {
  document.getElementById('modalFormTitulo').textContent = '➕ Nuevo Equipo';
  document.getElementById('formId').value          = '';
  document.getElementById('formMarca').value       = '';
  document.getElementById('formModelo').value      = '';
  document.getElementById('formSerial').value      = '';
  document.getElementById('formAntiguedad').value  = '';
  document.getElementById('formComentario').value  = '';
  document.getElementById('formUbicacion').value   = '';
  document.getElementById('formEstado').value      = '';
  // Poblar combobox de usuario desde USUARIOS_TPU (sin preselección)
  poblarSelectUsuario('formUsuario', '');
  // Modo nuevo: sin tabs, sin registrado_por
  document.getElementById('modalTabs').style.display          = 'none';
  document.getElementById('mpane-datos').style.display        = 'block';
  document.getElementById('mpane-trazabilidad').style.display = 'none';
  document.getElementById('wrapRegistradoPor').style.display  = 'none';
  abrirModal('modalForm');
}

// ══ UPDATE ════════════════════════════════════════════════════
function abrirModalEditar(e) {
  document.getElementById('modalFormTitulo').textContent = '✏️ Editar Equipo';
  document.getElementById('formId').value          = e.id;
  document.getElementById('formEtiqueta').value    = e.etiqueta      || '';
  document.getElementById('formMarca').value       = (e.marca||'').trim();
  document.getElementById('formModelo').value      = e.modelo        || '';
  document.getElementById('formSerial').value      = e.serial_number || '';
  document.getElementById('formComentario').value  = e.comentario    || '';
  document.getElementById('formUbicacion').value   = e.ubicacion     || '';
  document.getElementById('formEstado').value      = e.estado        || '';
  document.getElementById('formRegistradoPor').value = '';
  // Poblar combobox con el usuario actual preseleccionado
  poblarSelectUsuario('formUsuario', e.usuario || '');

  const fraw = e.antiguedad || '';
  if (fraw.includes('/')) {
    const [d,m,y] = fraw.split('/');
    document.getElementById('formAntiguedad').value = `${y}-${m}-${d}`;
  } else {
    document.getElementById('formAntiguedad').value = fraw;
  }
  // Modo edición: mostrar tabs y registrado_por
  document.getElementById('modalTabs').style.display         = 'flex';
  document.getElementById('wrapRegistradoPor').style.display = 'block';
  cambiarModalTab('datos');
  abrirModal('modalForm');
}

async function guardarEquipo() {
  const id            = document.getElementById('formId').value;
  const etiqueta      = document.getElementById('formEtiqueta').value.trim();
  const usuario       = document.getElementById('formUsuario').value.trim();  // select
  const marca         = document.getElementById('formMarca').value.trim();
  const modelo        = document.getElementById('formModelo').value.trim();
  const serial        = document.getElementById('formSerial').value.trim();
  const antiguedad    = document.getElementById('formAntiguedad').value;
  const comentario    = document.getElementById('formComentario').value.trim();
  const ubicacion     = document.getElementById('formUbicacion').value.trim();
  const estado        = document.getElementById('formEstado').value.trim();
  const registradoPor = document.getElementById('formRegistradoPor')?.value.trim() || '';

  if (!antiguedad) { showToast('La fecha de compra es obligatoria.', 'error'); return; }
  if (id && !registradoPor) { showToast('Debes seleccionar quién registra el cambio.', 'error'); return; }

  const btn = document.getElementById('btnGuardar');
  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const payload = { etiqueta, usuario, marca, modelo,
                      serial_number: serial, antiguedad, comentario,
                      ubicacion, estado, registrado_por: registradoPor };
    if (id) payload.id = parseInt(id);

    const res  = await fetch('save_equipo.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.ok) {
      cerrarModal('modalForm');
      showToast(data.msg, 'success');
      await cargarEquipos();
    } else {
      showToast(data.msg || 'Error al guardar.', 'error');
    }
  } catch (e) {
    showToast('Error de conexión: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = '💾 Guardar';
  }
}

// ══ BAJA (soft-delete) ═══════════════════════════════════════
function abrirModalBaja(equipo) {
  eliminarId = equipo.id;
  document.getElementById('bajaDetalle').textContent =
    `${equipo.etiqueta || 'Sin etiqueta'} — ${equipo.usuario || ''} — ${(equipo.marca||'').trim()} ${equipo.modelo || ''}`;
  document.getElementById('bajaMotivo').value         = '';
  document.getElementById('bajaMotivoOtroWrap').style.display = 'none';
  document.getElementById('bajaMotivoOtro').value     = '';
  document.getElementById('bajaRegistradoPor').value  = '';
  document.getElementById('bajaError').textContent    = '';
  document.getElementById('bajaRegError').textContent = '';

  document.getElementById('bajaMotivo').onchange = function() {
    document.getElementById('bajaMotivoOtroWrap').style.display =
      this.value === 'Otro' ? 'block' : 'none';
  };
  abrirModal('modalBaja');
}

async function confirmarBaja() {
  if (!eliminarId) return;
  const selectVal    = document.getElementById('bajaMotivo').value;
  const otroVal      = document.getElementById('bajaMotivoOtro').value.trim();
  const motivo       = selectVal === 'Otro' ? otroVal : selectVal;
  const registradoPor = document.getElementById('bajaRegistradoPor').value.trim();

  let valid = true;
  if (!motivo) {
    document.getElementById('bajaError').textContent =
      selectVal === 'Otro' ? 'Describe el motivo.' : 'Selecciona un motivo.';
    valid = false;
  } else {
    document.getElementById('bajaError').textContent = '';
  }
  if (!registradoPor) {
    document.getElementById('bajaRegError').textContent = 'Selecciona quién registra la baja.';
    valid = false;
  } else {
    document.getElementById('bajaRegError').textContent = '';
  }
  if (!valid) return;

  const btn = document.getElementById('btnConfirmarBaja');
  btn.disabled = true; btn.textContent = 'Procesando...';
  try {
    const res  = await fetch('baja_equipo.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: eliminarId, motivo_baja: motivo, registrado_por: registradoPor })
    });
    const data = await res.json();
    cerrarModal('modalBaja');
    if (data.ok) {
      showToast(data.msg, 'success');
      await cargarEquipos();
    } else {
      showToast(data.msg || 'Error al dar de baja.', 'error');
    }
  } catch (e) {
    showToast('Error de conexión: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = '📴 Confirmar baja';
    eliminarId = null;
  }
}

// ══ FILTROS ═══════════════════════════════════════════════════
function aplicarFiltros() {
  const q      = document.getElementById('inputBuscar').value.toLowerCase().trim();
  const marca  = document.getElementById('selectMarca').value.trim();
  const ant    = document.getElementById('selectAntiedad').value;
  const ubSel  = document.getElementById('selectUbicacion');
  const ubi    = ubSel ? ubSel.value.trim() : '';
  const estSel = document.getElementById('selectEstado');
  const est    = estSel ? estSel.value.trim() : '';

  equiposFiltrados = todosEquipos.filter(e => {
    const matchQ     = !q || [e.etiqueta, e.usuario, e.modelo, e.serial_number, e.marca]
                         .some(v => (v||'').toLowerCase().includes(q));
    const matchMarca = !marca || (e.marca||'').trim() === marca;
    const matchAnt   = ant === '' || parseInt(ant) === parseInt(e.anios);
    const matchUbi   = !ubi || (e.ubicacion||'').trim() === ubi;
    const matchEst   = !est || (e.estado||'').trim() === est;
    return matchQ && matchMarca && matchAnt && matchUbi && matchEst;
  });
  ordenarYRenderizar();
  actualizarFiltroActivo(q, marca, ant);
}

function limpiarFiltros() {
  document.getElementById('inputBuscar').value    = '';
  document.getElementById('selectMarca').value    = '';
  document.getElementById('selectAntiedad').value = '';
  const ubSel  = document.getElementById('selectUbicacion');
  if (ubSel) ubSel.value = '';
  const estSel = document.getElementById('selectEstado');
  if (estSel) estSel.value = '';
  aplicarFiltros();
}

// ══ ORDENAMIENTO ══════════════════════════════════════════════
function ordenarPor(col) {
  if (sortCol === col) sortAsc = !sortAsc;
  else { sortCol = col; sortAsc = true; }
  document.querySelectorAll('thead th span.sort-icon').forEach(s => s.textContent = '');
  document.querySelectorAll('thead th').forEach(th => th.classList.remove('sorted'));
  const el = document.getElementById('th-' + col);
  if (el) { el.textContent = sortAsc ? '▲' : '▼'; el.closest('th').classList.add('sorted'); }
  ordenarYRenderizar();
}

function ordenarYRenderizar() {
  const sorted = [...equiposFiltrados].sort((a, b) => {
    let va = a[sortCol] ?? '', vb = b[sortCol] ?? '';

    // Fechas en formato dd/mm/yyyy → convertir a timestamp para comparar correctamente
    if (sortCol === 'antiguedad') {
      const toTs = v => {
        if (!v || v === '—') return 0;
        const [d, m, y] = String(v).split('/');
        return new Date(`${y}-${m}-${d}`).getTime() || 0;
      };
      va = toTs(va); vb = toTs(vb);
      return sortAsc ? va - vb : vb - va;
    }

    if (['anios','meses','dias'].includes(sortCol)) {
      va = parseInt(va)||0; vb = parseInt(vb)||0;
      return sortAsc ? va-vb : vb-va;
    }
    return sortAsc
      ? String(va).localeCompare(String(vb))
      : String(vb).localeCompare(String(va));
  });
  renderTabla(sorted);
}

// ══ RENDER ════════════════════════════════════════════════════
function renderTabla(lista) {
  const tbody = document.getElementById('tbody');
  document.getElementById('contador').textContent = lista.length;

  if (lista.length === 0) {
    tbody.innerHTML = `<tr><td colspan="10"><div class="state-box">
      <div class="icon">🖥</div><p>Sin resultados para la búsqueda.</p>
    </div></td></tr>`;
    return;
  }

  window._equiposMap = {};
  lista.forEach(e => { window._equiposMap[e.id] = e; });

  tbody.innerHTML = lista.map(e => {
    const anios    = parseInt(e.anios) || 0;
    const badge    = getBadge(anios);
    const icono    = marcaIconos[(e.marca||'').trim()] || '💻';
    const esBaja   = e.estado === 'Dado de baja';
    const etiqueta = e.etiqueta
      ? `<span class="etiqueta-tag">${esc(e.etiqueta)}</span>`
      : `<span class="etiqueta-null">— sin etiqueta</span>`;

    return `<tr class="${esBaja ? 'row-baja' : ''}">
      <td data-label="Etiqueta">${etiqueta}</td>
      <td data-label="Usuario">${esc(e.usuario||'—')}</td>
      <td data-label="Marca">${icono} ${esc((e.marca||'').trim())}</td>
      <td data-label="Modelo" class="col-modelo"><span class="modelo-text" title="${esc(e.modelo||'')}">${esc(e.modelo||'—')}</span></td>
      <td data-label="Nº Serie" class="col-serial"><span class="serial-text">${esc(e.serial_number||'—')}</span></td>
      <td data-label="Fecha Compra"><span class="fecha-text">${esc(e.antiguedad||'—')}</span></td>
      <td data-label="Antigüedad">
        <span class="badge ${badge.cls}">${badge.lbl}</span>
        <div class="sub-text">${anios} a ${parseInt(e.meses)||0} m ${parseInt(e.dias)||0} d</div>
      </td>
      <td data-label="Ubicación">${esc(e.ubicacion||'—')}</td>
      <td data-label="Estado">
        ${getEstadoBadge(e.estado)}
        ${esBaja && e.fecha_baja ? `<div class="sub-text" title="${esc(e.motivo_baja||'')}">📅 ${esc(e.fecha_baja)}</div>` : ''}
      </td>
      <td>
        ${!esBaja ? `<button class="btn btn-accent btn-sm btn-icon" onclick="abrirPanel(window._equiposMap[${e.id}])" title="Ver mantenciones">🔧</button>` : ''}
        <button class="btn btn-traz btn-sm btn-icon" onclick="abrirModalTrazabilidad(window._equiposMap[${e.id}])" title="Ver trazabilidad">📍</button>
        <button class="btn btn-outline btn-sm btn-icon" onclick="abrirModalEditar(window._equiposMap[${e.id}])" title="Editar">✏️</button>
        ${!esBaja
          ? `<button class="btn btn-baja btn-sm btn-icon" onclick="abrirModalBaja(window._equiposMap[${e.id}])" title="Dar de baja">📴</button>`
          : ''
        }
      </td>
    </tr>`;
  }).join('');

  actualizarStats(lista);
}

function actualizarStats(lista) {
  document.getElementById('stats').innerHTML = '';
}

function actualizarSubtitle() {
  document.getElementById('subtitle').textContent = '';
}

function actualizarFiltroActivo(q, marca, ant) {
  const partes = [];
  if (q)     partes.push(`"${q}"`);
  if (marca) partes.push(`[${marca}]`);
  if (ant !== '') partes.push(ant==='0'?'< 1 año':`${ant}+ años`);
  const el = document.getElementById('filtroActivo');
  el.innerHTML = partes.map(p=>`<span class="filtro-tag">${esc(p)}</span>`).join(' ');
}

// ══ MODALES ═══════════════════════════════════════════════════
function abrirModal(id)  { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('active');
  });
});

// ══ TOAST ═════════════════════════════════════════════════════
function showToast(msg, tipo = 'success') {
  const t = document.getElementById('toast');
  t.textContent = (tipo === 'success' ? '✓ ' : '⚠ ') + msg;
  t.className   = `toast show ${tipo}`;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.classList.remove('show'); }, 3500);
}

// ══ HELPERS ═══════════════════════════════════════════════════
function getBadge(anios) {
  if (anios >= 4) return { lbl:'⚠ +4 años',  cls:'badge-red' };
  if (anios >= 3) return { lbl:'⚡ 3-4 años', cls:'badge-orange' };
  if (anios >= 2) return { lbl:'✓ 2-3 años',  cls:'badge-yellow' };
  if (anios >= 1) return { lbl:'✓ 1-2 años',  cls:'badge-green' };
  return               { lbl:'★ Nuevo',       cls:'badge-blue' };
}

function getEstadoBadge(estado) {
  if (!estado) return '<span style="color:#aaa;font-size:11px;">—</span>';
  const map = {
    'Nuevo':            'estado-nuevo',
    'Disponible':       'estado-disponible',
    'Asignado':         'estado-asignado',
    'Servicio técnico': 'estado-servicio',
    'Servicio tecnico': 'estado-servicio',
    'Dado de baja':     'estado-baja',
  };
  const cls = map[estado] || 'estado-otro';
  return `<span class="estado-equipo-badge ${cls}">${esc(estado)}</span>`;
}

function mostrarEstado(tipo, msg='') {
  const tbody = document.getElementById('tbody');
  if (tipo==='loading') {
    tbody.innerHTML=`<tr><td colspan="10"><div class="state-box"><div class="spinner"></div><p>Cargando datos...</p></div></td></tr>`;
  } else if (tipo==='error') {
    tbody.innerHTML=`<tr><td colspan="10"><div class="state-box error"><div class="icon">⚠</div><p>${esc(msg)}</p></div></td></tr>`;
  }
}

function esc(str) {
  return String(str??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ══ MODAL TRAZABILIDAD (desde botón tabla) ════════════════════
function abrirModalTrazabilidad(equipo) {
  const titulo = document.getElementById('modalTrazTitulo');
  const sub    = document.getElementById('modalTrazSub');
  titulo.textContent = `📍 ${equipo.etiqueta || 'Sin etiqueta'} — ${equipo.usuario || 'Sin usuario'}`;
  sub.textContent    = `${(equipo.marca||'').trim()} ${equipo.modelo||''} · ${equipo.serial_number||''}`;
  // Reutilizar el contenedor de trazabilidad del modal independiente
  const cont = document.getElementById('modalTrazContenedor');
  cont.innerHTML = '<div class="drawer-empty"><div class="spinner"></div><p>Cargando historial...</p></div>';
  abrirModal('modalTrazabilidad');
  cargarTrazabilidadEnContenedor(equipo.id, cont);
}

async function cargarTrazabilidadEnContenedor(equipoId, cont) {
  try {
    const res  = await fetch(`get_trazabilidad.php?equipo_id=${equipoId}`);
    const data = await res.json();
    if (!data.ok || !data.eventos.length) {
      cont.innerHTML = `<div class="drawer-empty">
        <div class="icon">📍</div>
        <p>Sin eventos registrados aún.<br>Los cambios futuros aparecerán aquí.</p>
      </div>`;
      return;
    }
    renderTrazabilidadHTML(cont, data.eventos, data.total);
  } catch (e) {
    cont.innerHTML = `<div class="drawer-empty"><div class="icon">⚠</div><p>Error: ${esc(e.message)}</p></div>`;
  }
}

// Función compartida que genera el HTML de trazabilidad
// (usada por modal tabla Y por pestaña del modal edición)
function renderTrazabilidadHTML(cont, eventos, total) {
  const eventoIcono = {
    'Asignacion':                  '👤',
    'Devolucion':                  '↩️',
    'Envio a servicio tecnico':    '🔧',
    'Retorno de servicio tecnico': '✅',
    'Baja':                        '📴',
    'Cambio de ubicacion':         '📍',
    'Registro inicial':            '🆕',
  };
  const eventoColor = {
    'Asignacion':                  'traz-azul',
    'Devolucion':                  'traz-gris',
    'Envio a servicio tecnico':    'traz-naranja',
    'Retorno de servicio tecnico': 'traz-verde',
    'Baja':                        'traz-rojo',
    'Cambio de ubicacion':         'traz-morado',
    'Registro inicial':            'traz-verde',
  };

  // Label legible para mostrar al usuario (con tildes)
  const eventoLabel = {
    'Asignacion':                  'Asignación',
    'Devolucion':                  'Devolución',
    'Envio a servicio tecnico':    'Envío a servicio técnico',
    'Retorno de servicio tecnico': 'Retorno de servicio técnico',
    'Baja':                        'Baja',
    'Cambio de ubicacion':         'Cambio de ubicación',
    'Registro inicial':            'Registro inicial',
  };

  cont.innerHTML = `
    <div class="traz-header">
      <span class="traz-total">${total} evento${total !== 1 ? 's' : ''} registrado${total !== 1 ? 's' : ''}</span>
    </div>
    <div class="traz-timeline">
      ${eventos.map(ev => {
        const icono = eventoIcono[ev.tipo_evento] || '📋';
        const cls   = eventoColor[ev.tipo_evento]  || 'traz-gris';
        const cambios = [];
        if (ev.usuario_anterior !== ev.usuario_nuevo && (ev.usuario_anterior || ev.usuario_nuevo))
          cambios.push(`Usuario: <strong>${esc(ev.usuario_anterior||'—')}</strong> → <strong>${esc(ev.usuario_nuevo||'—')}</strong>`);
        if (ev.estado_anterior !== ev.estado_nuevo && (ev.estado_anterior || ev.estado_nuevo))
          cambios.push(`Estado: <strong>${esc(ev.estado_anterior||'—')}</strong> → <strong>${esc(ev.estado_nuevo||'—')}</strong>`);
        if (ev.ubicacion_anterior !== ev.ubicacion_nueva && (ev.ubicacion_anterior || ev.ubicacion_nueva))
          cambios.push(`Ubicación: <strong>${esc(ev.ubicacion_anterior||'—')}</strong> → <strong>${esc(ev.ubicacion_nueva||'—')}</strong>`);
        return `<div class="traz-item">
          <div class="traz-dot ${cls}">${icono}</div>
          <div class="traz-body">
            <div class="traz-tipo">${esc(eventoLabel[ev.tipo_evento] || ev.tipo_evento)}</div>
            <div class="traz-meta">
              <span>📅 ${esc(ev.fecha_evento)}</span>
              <span>👤 ${esc(ev.registrado_por)}</span>
            </div>
            ${cambios.length ? `<div class="traz-cambios">${cambios.join(' &nbsp;·&nbsp; ')}</div>` : ''}
            ${ev.observacion ? `<div class="traz-obs">${esc(ev.observacion)}</div>` : ''}
          </div>
        </div>`;
      }).join('')}
    </div>`;
}

// ══ TABS DEL MODAL EDITAR ═════════════════════════════════════
function cambiarModalTab(tab) {
  ['datos','trazabilidad'].forEach(t => {
    document.getElementById(`mtab-${t}`)?.classList.toggle('active', t === tab);
    document.getElementById(`mpane-${t}`).style.display = t === tab ? 'block' : 'none';
  });
  if (tab === 'trazabilidad') {
    const id = document.getElementById('formId').value;
    if (id) cargarTrazabilidad(parseInt(id));
  }
}

async function cargarTrazabilidad(equipoId) {
  const cont = document.getElementById('trazabilidadContenedor');
  cont.innerHTML = '<div class="drawer-empty"><div class="spinner"></div><p>Cargando historial...</p></div>';
  try {
    const res  = await fetch(`get_trazabilidad.php?equipo_id=${equipoId}`);
    const data = await res.json();
    if (!data.ok || !data.eventos.length) {
      cont.innerHTML = `<div class="drawer-empty">
        <div class="icon">📍</div>
        <p>Sin eventos de trazabilidad registrados aún.<br>
        Los cambios futuros aparecerán aquí.</p>
      </div>`;
      return;
    }
    renderTrazabilidadHTML(cont, data.eventos, data.total);
  } catch (e) {
    cont.innerHTML = `<div class="drawer-empty"><div class="icon">⚠</div><p>Error: ${esc(e.message)}</p></div>`;
  }
}

// ══ TRACKING POR USUARIO ══════════════════════════════════════

// Historial histórico del Excel (N = nuevo, U = usado, X = desconocido)
const historialExcel = {
  'lyagi':          [{eq:'1°',tipo:'N'}],
  'xjaramillo':     [{eq:'1°',tipo:'N'}],
  'edoerr':         [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'rtenberg':       [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'kgonzalez':      [{eq:'1°',tipo:'N'}],
  'lnilo':          [{eq:'1°',tipo:'N'}],
  'icontreras':     [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'pgarcia':        [{eq:'1°',tipo:'U'}],
  'cnaveillan':     [{eq:'1°',tipo:'N'}],
  'jaranda':        [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'vureta':         [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'nllauquen':      [{eq:'1°',tipo:'U'}],
  'amartinez':      [{eq:'1°',tipo:'N'}],
  'droso':          [{eq:'1°',tipo:'U'}],
  'tfreitas':       [{eq:'1°',tipo:'N'}],
  'pecheverria':    [{eq:'1°',tipo:'N'}],
  'jclaro':         [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'fvargas':        [{eq:'1°',tipo:'N'}],
  'erogel':         [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'jreyes':         [{eq:'1°',tipo:'N'}],
  'mheredia':       [{eq:'1°',tipo:'N'}],
  'mperrot':        [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'U'}],
  'kduran':         [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'mossandon':      [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'X'}],
  'pestroz':        [{eq:'1°',tipo:'N'}],
  'jwalbaum':       [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'agarcia':        [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'cgarcia':        [{eq:'1°',tipo:'N'}],
  'fvelasquez':     [{eq:'1°',tipo:'N'}],
  'pnavarrete':     [{eq:'1°',tipo:'N'}],
  'dmontecinos':    [{eq:'1°',tipo:'N'}],
  'dvergudo':       [{eq:'1°',tipo:'N'}],
  'cflores':        [{eq:'1°',tipo:'N'}],
  'agalarce':       [{eq:'1°',tipo:'N'}],
  'emorales':       [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'U'}],
  'pnuñez':         [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'vsepulveda':     [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'aagudelo':       [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'N'}],
  'psaffery':       [{eq:'1°',tipo:'N'},{eq:'2°',tipo:'N'}],
  'lmansilla':      [{eq:'1°',tipo:'N'}],
  'lsoto':          [{eq:'1°',tipo:'U'},{eq:'2°',tipo:'U'}],
  'mromero':        [{eq:'1°',tipo:'U'}],
  'mugarte':        [{eq:'1°',tipo:'U'}],
  'cortega':        [{eq:'1°',tipo:'U'}],
  'npinto':         [{eq:'1°',tipo:'N'}],
  'lalvarez':       [{eq:'1°',tipo:'U'}],
  'ebarrientos':    [{eq:'1°',tipo:'N'}],
  'petchegaray':    [{eq:'1°',tipo:'U'}],
  'csilva':         [{eq:'1°',tipo:'U'}],
  'jcelis':         [{eq:'1°',tipo:'N'}],
};

function abrirModalTrackingUsuario() {
  // Poblar select con usuarios únicos de la BD + historialExcel
  const sel = document.getElementById('trackingUsuarioSelect');
  sel.innerHTML = '<option value="">— Selecciona un usuario —</option>';

  // Usuarios desde equipos cargados en memoria
  const usuariosBD = [...new Set(
    todosEquipos.map(e => (e.usuario||'').trim().toLowerCase()).filter(Boolean)
  )].sort();

  // Usuarios del historial Excel que no estén ya
  const usuariosExcel = Object.keys(historialExcel);
  const todos = [...new Set([...usuariosBD, ...usuariosExcel])].sort();

  todos.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u;
    opt.textContent = u;
    sel.appendChild(opt);
  });

  document.getElementById('trackingResultado').innerHTML = `
    <div class="tracking-empty">
      <div style="font-size:32px;margin-bottom:8px;">👤</div>
      <p>Selecciona un usuario para ver su historial de equipos</p>
    </div>`;

  abrirModal('modalTrackingUsuario');
}

async function cargarTrackingUsuario(usuario) {
  if (!usuario) return;
  const cont = document.getElementById('trackingResultado');
  cont.innerHTML = '<div class="drawer-empty"><div class="spinner"></div><p>Cargando...</p></div>';

  try {
    // ── 1. Equipo actual en BD ──────────────────────────────
    const equipoActual = todosEquipos.filter(
      e => (e.usuario||'').trim().toLowerCase() === usuario.toLowerCase()
    );

    // ── 2. Trazabilidad: equipos por los que pasó ───────────
    const resTraz = await fetch(`get_trazabilidad_usuario.php?usuario=${encodeURIComponent(usuario)}`);
    const dataTraz = await resTraz.json();
    const eventos  = dataTraz.ok ? dataTraz.eventos : [];

    // ── 3. Historial Excel ──────────────────────────────────
    const excelData = historialExcel[usuario.toLowerCase()] || null;

    // ── Contadores ──────────────────────────────────────────
    const totalEquipos = Math.max(
      equipoActual.length,
      excelData ? excelData.length : 0,
      eventos.length > 0 ? 1 : 0
    );
    const nuevos = excelData ? excelData.filter(x => x.tipo === 'N').length : '—';
    const usados = excelData ? excelData.filter(x => x.tipo === 'U').length : '—';

    // ── Render ──────────────────────────────────────────────
    let html = `<div class="tracking-user-header">
      <div class="tracking-user-name">👤 ${esc(usuario)}</div>
      <div class="tracking-kpis">
        <div class="tracking-kpi">
          <div class="tracking-kpi-num">${excelData ? excelData.length : equipoActual.length}</div>
          <div class="tracking-kpi-lbl">equipos totales</div>
        </div>
        <div class="tracking-kpi">
          <div class="tracking-kpi-num" style="color:#057F79">${nuevos}</div>
          <div class="tracking-kpi-lbl">nuevos</div>
        </div>
        <div class="tracking-kpi">
          <div class="tracking-kpi-num" style="color:#8a6d3b">${usados}</div>
          <div class="tracking-kpi-lbl">usados</div>
        </div>
      </div>
    </div>`;

    // Equipo actual
    if (equipoActual.length > 0) {
      html += `<div class="tracking-section-title">💻 Equipo actual</div>`;
      html += equipoActual.map(e => `
        <div class="tracking-equipo-card tracking-actual">
          <div class="tracking-equipo-top">
            <span class="etiqueta-tag">${esc(e.etiqueta||'—')}</span>
            ${getEstadoBadge(e.estado)}
          </div>
          <div class="tracking-equipo-info">
            ${esc((e.marca||'').trim())} ${esc(e.modelo||'')}
            <span style="color:var(--sb-text-light)"> · ${esc(e.ubicacion||'—')}</span>
          </div>
          <div class="tracking-equipo-meta">
            📅 Desde ${esc(e.antiguedad||'—')} &nbsp;·&nbsp;
            🔢 S/N: <span style="font-family:monospace;font-size:11px">${esc(e.serial_number||'—')}</span>
          </div>
        </div>`).join('');
    } else {
      html += `<div class="tracking-section-title">💻 Equipo actual</div>
        <p style="font-size:13px;color:var(--sb-text-light);padding:10px 0">Sin equipo asignado actualmente.</p>`;
    }

    // Historial Excel
    if (excelData) {
      html += `<div class="tracking-section-title">📋 Historial registrado (Excel)</div>
        <div class="tracking-excel-grid">
          ${excelData.map(h => {
            const cls  = h.tipo === 'N' ? 'excel-nuevo' : h.tipo === 'U' ? 'excel-usado' : 'excel-otro';
            const lbl  = h.tipo === 'N' ? 'Nuevo' : h.tipo === 'U' ? 'Usado' : 'Desc.';
            return `<div class="tracking-excel-item ${cls}">
              <div class="tracking-excel-ord">${h.eq}</div>
              <div class="tracking-excel-tipo">${lbl}</div>
            </div>`;
          }).join('')}
        </div>`;
    }

    // Trazabilidad
    if (eventos.length > 0) {
      html += `<div class="tracking-section-title">📍 Trazabilidad del sistema (${eventos.length} evento${eventos.length!==1?'s':''})</div>
        <div class="traz-timeline">
          ${eventos.map(ev => {
            const iconoMap = {
              'Asignacion':'👤','Devolucion':'↩️','Envio a servicio tecnico':'🔧',
              'Retorno de servicio tecnico':'✅','Baja':'📴','Cambio de ubicacion':'📍','Registro inicial':'🆕'
            };
            const labelMap = {
              'Asignacion':'Asignación','Devolucion':'Devolución',
              'Envio a servicio tecnico':'Envío a servicio técnico',
              'Retorno de servicio tecnico':'Retorno de servicio técnico',
              'Baja':'Baja','Cambio de ubicacion':'Cambio de ubicación','Registro inicial':'Registro inicial'
            };
            return `<div class="traz-item">
              <div class="traz-dot traz-azul">${iconoMap[ev.tipo_evento]||'📋'}</div>
              <div class="traz-body">
                <div class="traz-tipo">${esc(labelMap[ev.tipo_evento]||ev.tipo_evento)}</div>
                <div class="traz-meta">
                  <span>📅 ${esc(ev.fecha_evento)}</span>
                  <span>🖥 Equipo ID: ${ev.equipo_id}</span>
                </div>
                ${ev.estado_nuevo ? `<div class="traz-cambios">Estado → <strong>${esc(ev.estado_nuevo)}</strong></div>` : ''}
              </div>
            </div>`;
          }).join('')}
        </div>`;
    }

    cont.innerHTML = html;

  } catch(e) {
    cont.innerHTML = `<div class="drawer-empty"><div class="icon">⚠</div><p>Error: ${esc(e.message)}</p></div>`;
  }
}

// ══ INIT ══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('inputBuscar').addEventListener('input', aplicarFiltros);
  document.getElementById('selectMarca').addEventListener('change', aplicarFiltros);
  document.getElementById('selectAntiedad').addEventListener('change', aplicarFiltros);
  const estSel = document.getElementById('selectEstado');
  if (estSel) estSel.addEventListener('change', aplicarFiltros);
  const ubSel = document.getElementById('selectUbicacion');
  if (ubSel) ubSel.addEventListener('change', aplicarFiltros);
  // Cargar usuarios y equipos en paralelo al iniciar
  Promise.all([cargarUsuarios(), cargarEquipos()]);
});

/* ================================================
   MÓDULO MANTENCIONES
   Panel lateral + Dashboard + CRUD
================================================ */

// ── Estado mantenciones ───────────────────────────────────────
let equipoActivo       = null;   // equipo seleccionado
let mantenciones       = [];
let editandoMantId     = null;
let dashboardCargado   = false;

// ══ ABRIR / CERRAR PANEL LATERAL ═════════════════════════════
function abrirPanel(equipo) {
  // Marcar fila seleccionada
  document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected-row'));
  const filas = document.querySelectorAll('tbody tr');
  filas.forEach(tr => {
    if (tr.querySelector(`[onclick*="_equiposMap[${equipo.id}]"]`))
      tr.classList.add('selected-row');
  });

  equipoActivo   = equipo;
  editandoMantId = null;
  dashboardCargado = false;

  // Header del panel
  document.getElementById('drawerEquipoNombre').textContent =
    (equipo.etiqueta || 'Sin etiqueta') + ' — ' + (equipo.usuario || '');
  document.getElementById('drawerEquipoInfo').textContent =
    ((equipo.marca || '').trim()) + ' ' + (equipo.modelo || '');

  document.getElementById('drawerOverlay').classList.add('active');
  document.getElementById('drawerPanel').classList.add('active');

  // Mostrar tab historial por defecto
  cambiarTab('historial');
}

function cerrarPanel() {
  document.getElementById('drawerOverlay').classList.remove('active');
  document.getElementById('drawerPanel').classList.remove('active');
  document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected-row'));
  equipoActivo = null;
}

function cambiarTab(tab) {
  document.querySelectorAll('.drawer-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.drawer-pane').forEach(p => p.style.display = 'none');
  document.getElementById('tab-' + tab).classList.add('active');
  document.getElementById('pane-' + tab).style.display = 'block';

  if (tab === 'historial') cargarMantenciones();
  if (tab === 'nueva')     prepararFormNueva();
  if (tab === 'dashboard' && !dashboardCargado) cargarDashboard();
}

// ══ READ: cargar historial ════════════════════════════════════
async function cargarMantenciones() {
  if (!equipoActivo) return;
  const contenedor = document.getElementById('timelineContenedor');
  contenedor.innerHTML = '<div class="drawer-empty"><div class="spinner"></div></div>';

  try {
    const res  = await fetch(`get_mantenciones.php?equipo_id=${equipoActivo.id}`);
    const data = await res.json();
    mantenciones = data.mantenciones || [];
    renderTimeline();
  } catch (e) {
    contenedor.innerHTML = `<div class="drawer-empty error"><div class="icon">⚠</div><p>Error al cargar: ${esc(e.message)}</p></div>`;
  }
}

function renderTimeline() {
  const contenedor = document.getElementById('timelineContenedor');
  if (mantenciones.length === 0) {
    contenedor.innerHTML = `<div class="drawer-empty">
      <div class="icon">🔧</div>
      <p>Sin mantenciones registradas.<br>Usa "Nueva Mantención" para agregar una.</p>
    </div>`;
    return;
  }

  contenedor.innerHTML = `<div class="timeline">${mantenciones.map(m => {
    const dotClass   = m.estado === 'Completada' ? 'dot-completada' : m.estado === 'En proceso' ? 'dot-proceso' : 'dot-pendiente';
    const tipoClass  = 'tipo-' + (m.tipo || '').toLowerCase().replace(' ','');
    const estadoClass= 'estado-' + (m.estado || '').toLowerCase().replace(' ','');
    const proxima    = m.proxima_revision
      ? `<span class="proxima-revision-badge">📅 Próx: ${esc(m.proxima_revision)}</span>` : '';

    return `<div class="timeline-item">
      <div class="timeline-dot ${dotClass}"></div>
      <div class="timeline-card">
        <div class="timeline-card-header">
          <div>
            <span class="tipo-badge ${tipoClass}">${esc(m.tipo)}</span>
            <span class="estado-badge ${estadoClass}" style="margin-left:6px">${esc(m.estado)}</span>
          </div>
          <span class="timeline-fecha">📅 ${esc(m.fecha)}</span>
        </div>
        ${m.descripcion ? `<div class="timeline-desc">${esc(m.descripcion)}</div>` : ''}
        <div class="timeline-meta">
          ${m.tecnico ? `<span>👤 ${esc(m.tecnico)}</span>` : ''}
          ${proxima}
        </div>
        <div class="timeline-actions">
          <button class="btn btn-outline btn-sm" onclick="editarMantencion(${m.id})">✏️ Editar</button>
          <button class="btn btn-danger btn-sm"  onclick="eliminarMantencion(${m.id})">🗑️</button>
        </div>
      </div>
    </div>`;
  }).join('')}</div>`;
}

// ══ CREATE / UPDATE mantención ════════════════════════════════
function prepararFormNueva() {
  editandoMantId = null;
  document.getElementById('mantFormTitulo').textContent = '➕ Nueva Mantención';
  document.getElementById('mantFecha').value          = new Date().toISOString().split('T')[0];
  document.getElementById('mantTipo').value           = '';
  document.getElementById('mantDescripcion').value    = '';
  document.getElementById('mantTecnico').value        = '';
  document.getElementById('mantEstado').value         = 'Completada';
  document.getElementById('mantProxima').value        = '';
}

function editarMantencion(id) {
  const m = mantenciones.find(x => x.id === id);
  if (!m) return;
  editandoMantId = id;

  cambiarTab('nueva');
  document.getElementById('mantFormTitulo').textContent = '✏️ Editar Mantención';

  // Convertir fecha dd/mm/yyyy → yyyy-mm-dd
  const toInput = f => {
    if (!f) return '';
    if (f.includes('/')) { const [d,mo,y] = f.split('/'); return `${y}-${mo}-${d}`; }
    return f;
  };

  document.getElementById('mantFecha').value       = toInput(m.fecha);
  document.getElementById('mantTipo').value        = m.tipo        || '';
  document.getElementById('mantDescripcion').value = m.descripcion || '';
  document.getElementById('mantTecnico').value     = m.tecnico     || '';
  document.getElementById('mantEstado').value      = m.estado      || 'Completada';
  document.getElementById('mantProxima').value     = toInput(m.proxima_revision);
}

async function guardarMantencion() {
  if (!equipoActivo) return;

  const fecha    = document.getElementById('mantFecha').value;
  const tipo     = document.getElementById('mantTipo').value;
  const desc     = document.getElementById('mantDescripcion').value.trim();
  const tecnico  = document.getElementById('mantTecnico').value.trim();
  const estado   = document.getElementById('mantEstado').value;
  const proxima  = document.getElementById('mantProxima').value;

  if (!fecha || !tipo) { showToast('Fecha y tipo son obligatorios.', 'error'); return; }

  const btn = document.getElementById('btnGuardarMant');
  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const payload = {
      equipo_id: equipoActivo.id,
      fecha, tipo, descripcion: desc, tecnico, estado,
      proxima_revision: proxima
    };
    if (editandoMantId) payload.id = editandoMantId;

    const res  = await fetch('save_mantencion.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.ok) {
      showToast(data.msg, 'success');
      editandoMantId   = null;
      dashboardCargado = false;
      cambiarTab('historial');
    } else {
      showToast(data.msg || 'Error al guardar.', 'error');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = '💾 Guardar';
  }
}

// ══ DELETE mantención ══════════════════════════════════════════
async function eliminarMantencion(id) {
  if (!confirm('¿Eliminar esta mantención?')) return;
  try {
    const res  = await fetch('delete_mantencion.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.ok) {
      showToast(data.msg, 'success');
      dashboardCargado = false;
      cargarMantenciones();
    } else {
      showToast(data.msg || 'Error.', 'error');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  }
}

// ══ DASHBOARD ══════════════════════════════════════════════════
async function cargarDashboard() {
  const contenedor = document.getElementById('dashContenedor');
  contenedor.innerHTML = '<div class="drawer-empty"><div class="spinner"></div><p>Cargando estadísticas...</p></div>';

  try {
    const res  = await fetch('get_mantenciones.php?dashboard=1');
    const data = await res.json();
    dashboardCargado = true;
    renderDashboard(data);
  } catch (e) {
    contenedor.innerHTML = `<div class="drawer-empty"><div class="icon">⚠</div><p>Error: ${esc(e.message)}</p></div>`;
  }
}

function renderDashboard(d) {
  const pendientes  = (d.por_estado.find(x => x.estado === 'Pendiente')   || {}).cantidad || 0;
  const enProceso   = (d.por_estado.find(x => x.estado === 'En proceso')  || {}).cantidad || 0;
  const completadas = (d.por_estado.find(x => x.estado === 'Completada')  || {}).cantidad || 0;

  const maxTipo = Math.max(...d.por_tipo.map(x => x.cantidad), 1);
  const maxTop  = Math.max(...d.top_equipos.map(x => x.cantidad), 1);

  // Gráfico de barras por mes
  const meses = d.por_mes;
  const maxMes = Math.max(...meses.map(x => x.cantidad), 1);
  const mesNombres = { '01':'Ene','02':'Feb','03':'Mar','04':'Abr','05':'May','06':'Jun',
                       '07':'Jul','08':'Ago','09':'Sep','10':'Oct','11':'Nov','12':'Dic' };

  document.getElementById('dashContenedor').innerHTML = `

    <!-- Stats globales -->
    <div class="dash-grid">
      <div class="dash-stat">
        <div class="num">${d.total}</div>
        <div class="lbl">Total</div>
      </div>
      <div class="dash-stat">
        <div class="num" style="color:var(--badge-green-txt)">${completadas}</div>
        <div class="lbl">Completadas</div>
      </div>
      <div class="dash-stat">
        <div class="num" style="color:var(--badge-red-txt)">${pendientes + enProceso}</div>
        <div class="lbl">Pendientes</div>
      </div>
    </div>

    <!-- Por tipo -->
    <div class="dash-section">
      <h4>Por tipo</h4>
      <div class="bar-chart">
        ${d.por_tipo.map(x => `
          <div class="bar-row">
            <span class="bar-label">${esc(x.tipo)}</span>
            <div class="bar-track"><div class="bar-fill" style="width:${Math.round(x.cantidad/maxTipo*100)}%"></div></div>
            <span class="bar-count">${x.cantidad}</span>
          </div>`).join('')}
      </div>
    </div>

    <!-- Actividad mensual -->
    <div class="dash-section">
      <h4>Actividad últimos 12 meses</h4>
      <div class="bar-chart">
        ${meses.map(x => {
          const [anio, mes] = x.mes.split('-');
          return `<div class="bar-row">
            <span class="bar-label">${mesNombres[mes] || mes} ${anio}</span>
            <div class="bar-track"><div class="bar-fill accent" style="width:${Math.round(x.cantidad/maxMes*100)}%"></div></div>
            <span class="bar-count">${x.cantidad}</span>
          </div>`;
        }).join('')}
      </div>
    </div>

    <!-- Top equipos -->
    <div class="dash-section">
      <h4>Equipos con más mantenciones</h4>
      <div class="bar-chart">
        ${d.top_equipos.map(x => `
          <div class="bar-row">
            <span class="bar-label">${esc(x.etiqueta || x.usuario || '—')}</span>
            <div class="bar-track"><div class="bar-fill" style="width:${Math.round(x.cantidad/maxTop*100)}%"></div></div>
            <span class="bar-count">${x.cantidad}</span>
          </div>`).join('')}
      </div>
    </div>

    <!-- Próximas revisiones -->
    ${d.proximas_revisiones.length > 0 ? `
    <div class="dash-section">
      <h4>⏰ Próximas revisiones (60 días)</h4>
      <div class="revision-list">
        ${d.proximas_revisiones.map(x => {
          const cls = x.dias_restantes <= 7 ? 'urgent' : x.dias_restantes <= 20 ? 'warning' : '';
          return `<div class="revision-item">
            <div class="revision-days ${cls}">${x.dias_restantes}d</div>
            <div class="revision-info">
              <strong>${esc(x.etiqueta || '—')} — ${esc(x.usuario || '')}</strong>
              <span>👤 ${esc(x.tecnico || '—')} · 📅 ${esc(x.proxima_revision)}</span>
            </div>
          </div>`;
        }).join('')}
      </div>
    </div>` : ''}
  `;
}