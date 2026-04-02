<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario Equipos Computacionales</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/equipos.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>

  <!-- ══ HEADER ══════════════════════════════════════════════════ -->
  <div class="header">
    <div class="header-brand">
      <div class="logo-icon">💻</div>
      <div>
        <h1>Inventario Equipos Computacionales</h1>
        <p id="subtitle"></p>
      </div>
    </div>
    <div class="header-stats" id="stats"></div>
  </div>

  <div class="container">

    <!-- ══ DASHBOARD ════════════════════════════════════════════ -->
    <div class="dash-top-label">
      <span class="section-eyebrow">📊 Resumen ejecutivo</span>
      <div style="display:flex;gap:8px;">
        <button class="btn-tracking-user" onclick="abrirModalReporteUsuarios()">📊 Reporte usuarios</button>
        <button class="btn-tracking-user" onclick="abrirModalTrackingUsuario()">👤 Tracking usuario</button>
        <button class="dash-refresh-btn" onclick="cargarEquipos()">↻ Actualizar</button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="kpi-grid" id="kpiGrid">
      <div class="kpi-card kpi-total">
        <div class="kpi-icon-wrap">🖥</div>
        <div class="kpi-body">
          <div class="kpi-num" id="kpiTotal">—</div>
          <div class="kpi-lbl">Activos en sistema</div>
        </div>
        <div class="kpi-stripe kpi-stripe-total"></div>
      </div>
      <div class="kpi-card kpi-critical">
        <div class="kpi-icon-wrap">⚠</div>
        <div class="kpi-body">
          <div class="kpi-num" id="kpiCritical">—</div>
          <div class="kpi-lbl">Críticos (+4 años)</div>
        </div>
        <div class="kpi-stripe kpi-stripe-critical"></div>
      </div>
      <div class="kpi-card kpi-warn">
        <div class="kpi-icon-wrap">⚡</div>
        <div class="kpi-body">
          <div class="kpi-num" id="kpiWarn">—</div>
          <div class="kpi-lbl">Por renovar (3‑4 a)</div>
        </div>
        <div class="kpi-stripe kpi-stripe-warn"></div>
      </div>
      <div class="kpi-card kpi-ok">
        <div class="kpi-icon-wrap">✓</div>
        <div class="kpi-body">
          <div class="kpi-num" id="kpiOk">—</div>
          <div class="kpi-lbl">En buen estado</div>
        </div>
        <div class="kpi-stripe kpi-stripe-ok"></div>
      </div>
      <div class="kpi-card kpi-new">
        <div class="kpi-icon-wrap">★</div>
        <div class="kpi-body">
          <div class="kpi-num" id="kpiNew">—</div>
          <div class="kpi-lbl">Nuevos (&lt;1 año)</div>
        </div>
        <div class="kpi-stripe kpi-stripe-new"></div>
      </div>
    </div>

    <!-- KPIs operativos por estado -->
    <div class="kpi-op-grid" id="kpiOperativos">
      <!-- Llenado por JS -->
    </div>

    <!-- Ubicaciones + Top críticos -->
    <div class="dash-middle">

      <!-- Tarjetas por ubicación -->
      <div class="dash-col dash-col-wide">
        <div class="dash-card">
          <div class="dash-card-header">
            <span class="dash-card-title">📍 Equipos por ubicación</span>
          </div>
          <div id="ubicacionCards" class="ubicacion-grid">
            <div class="ubi-skeleton"></div>
            <div class="ubi-skeleton"></div>
            <div class="ubi-skeleton"></div>
          </div>

          <!-- Panel de detalle al hacer clic en una sede -->
          <div id="ubiDetalle" class="ubi-detalle-panel" style="display:none;">
            <div class="ubi-detalle-header">
              <span id="ubiDetalleTitulo"></span>
              <button class="ubi-detalle-close" onclick="cerrarDetalleUbi()">✕ cerrar</button>
            </div>
            <div id="ubiDetalleBody"></div>
          </div>
        </div>

        <!-- Marcas barra -->
        <div class="dash-card" style="margin-top:18px;">
          <div class="dash-card-header">
            <span class="dash-card-title">🏷 Distribución por marca</span>
          </div>
          <div id="marcasBars" class="marcas-bars-wrap"></div>
        </div>
      </div>

      <!-- Top 5 críticos -->
      <div class="dash-col dash-col-narrow">
        <div class="dash-card dash-card-alert">
          <div class="dash-card-header">
            <span class="dash-card-title">🚨 Top 5 más antiguos</span>
            <span class="alert-pill">Requiere atención</span>
          </div>
          <div id="topCriticos" class="criticos-list">
            <div class="critico-skeleton"></div>
            <div class="critico-skeleton"></div>
            <div class="critico-skeleton"></div>
          </div>
        </div>
      </div>

    </div>

    <!-- ══ SECCIÓN ADMINISTRACIÓN ════════════════════════════════ -->
    <div class="section-divider">
      <div class="section-divider-line"></div>
      <span class="section-divider-label">⚙ Administración de inventario</span>
      <div class="section-divider-line"></div>
    </div>

    <!-- FILTROS -->
    <div class="card">
      <div class="card-title">🔍 Filtros de búsqueda</div>
      <div class="filters">
        <div class="filter-group">
          <label>Buscar</label>
          <input type="text" id="inputBuscar" placeholder="Etiqueta, usuario, modelo, S/N...">
        </div>
        <div class="filter-group" style="max-width:150px;">
          <label>Marca</label>
          <select id="selectMarca"><option value="">Todas las marcas</option></select>
        </div>
        <div class="filter-group" style="max-width:185px;">
          <label>Antigüedad</label>
          <select id="selectAntiedad">
            <option value="">Todos</option>
            <option value="4">⚠ +4 años (reemplazar)</option>
            <option value="3">⚡ 3-4 años</option>
            <option value="2">✓ 2-3 años</option>
            <option value="1">✓ 1-2 años</option>
            <option value="0">★ Menos de 1 año</option>
          </select>
        </div>
        <div class="filter-group" style="max-width:160px;">
          <label>Ubicación</label>
          <select id="selectUbicacion"><option value="">Todas</option></select>
        </div>
        <div class="filter-group" style="max-width:170px;">
          <label>Estado</label>
          <select id="selectEstado">
            <option value="">Todos los estados</option>
            <option value="Nuevo">📦 Nuevo</option>
            <option value="Disponible">🟢 Disponible</option>
            <option value="Asignado">🔵 Asignado</option>
            <option value="Servicio técnico">🟡 Servicio técnico</option>
            <option value="Dado de baja">🔴 Dado de baja</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="aplicarFiltros()">Filtrar</button>
        <button class="btn btn-outline" onclick="limpiarFiltros()">Limpiar</button>
      </div>
    </div>

    <!-- TABLA -->
    <div class="table-card">
      <div class="table-bar">
        <span>Mostrando <strong id="contador">0</strong> registros</span>
        <div class="table-actions">
          <span id="filtroActivo"></span>
          <button class="btn btn-secondary" onclick="abrirModalNuevo()">＋ Nuevo Equipo</button>
        </div>
      </div>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th onclick="ordenarPor('etiqueta')">Etiqueta <span class="sort-icon" id="th-etiqueta"></span></th>
              <th onclick="ordenarPor('usuario')">Usuario <span class="sort-icon" id="th-usuario"></span></th>
              <th onclick="ordenarPor('marca')">Marca <span class="sort-icon" id="th-marca"></span></th>
              <th class="col-modelo-th" onclick="ordenarPor('modelo')">Modelo <span class="sort-icon" id="th-modelo"></span></th>
              <th class="col-serial-th">Nº Serie</th>
              <th onclick="ordenarPor('antiguedad')">Fecha Compra <span class="sort-icon" id="th-antiguedad"></span></th>
              <th onclick="ordenarPor('anios')">Antigüedad <span class="sort-icon" id="th-anios"></span></th>
              <th onclick="ordenarPor('ubicacion')">Ubicación <span class="sort-icon" id="th-ubicacion"></span></th>
              <th onclick="ordenarPor('estado')">Estado <span class="sort-icon" id="th-estado"></span></th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tbody"></tbody>
        </table>
      </div>
    </div>

  </div><!-- /container -->


  <!-- ══ PANEL LATERAL MANTENCIONES ══════════════════════════════ -->
  <div class="drawer-overlay" id="drawerOverlay" onclick="cerrarPanel()"></div>
  <div class="drawer" id="drawerPanel">
    <div class="drawer-header">
      <div class="drawer-header-info">
        <h2 id="drawerEquipoNombre">—</h2>
        <p id="drawerEquipoInfo">—</p>
      </div>
      <button class="drawer-close" onclick="cerrarPanel()">✕</button>
    </div>
    <div class="drawer-tabs">
      <div class="drawer-tab active" id="tab-historial" onclick="cambiarTab('historial')">📋 Historial</div>
      <div class="drawer-tab" id="tab-nueva" onclick="cambiarTab('nueva')">➕ Nueva</div>
      <div class="drawer-tab" id="tab-dashboard" onclick="cambiarTab('dashboard')">📊 Dashboard</div>
    </div>
    <div class="drawer-body">
      <div class="drawer-pane" id="pane-historial">
        <div id="timelineContenedor">
          <div class="drawer-empty"><div class="spinner"></div></div>
        </div>
      </div>
      <div class="drawer-pane" id="pane-nueva" style="display:none">
        <div class="mantencion-form">
          <h3 id="mantFormTitulo">➕ Nueva Mantención</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Fecha <span style="color:#a94442">*</span></label>
              <input type="date" id="mantFecha">
            </div>
            <div class="form-group">
              <label>Tipo <span style="color:#a94442">*</span></label>
              <select id="mantTipo">
                <option value="">Seleccionar...</option>
                <option>Preventiva</option><option>Correctiva</option>
                <option>Limpieza</option><option>Formateo</option>
                <option>Garantia</option><option>Otro</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Responsable</label>
              <select id="mantTecnico">
                <option value="">Seleccionar...</option>
                <option value="Luis Yagi">Luis Yagi</option>
                <option value="Fabian Velasquez">Fabian Velasquez</option>
                <option value="Juan Felipe Celis">Juan Felipe Celis</option>
              </select>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select id="mantEstado">
                <option value="Completada">Completada</option>
                <option value="En proceso">En proceso</option>
                <option value="Pendiente">Pendiente</option>
              </select>
            </div>
          </div>
          <div class="form-row single">
            <div class="form-group">
              <label>Descripción / Detalle</label>
              <textarea id="mantDescripcion" placeholder="¿Qué se hizo?" rows="3"></textarea>
            </div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
            <button class="btn btn-outline" onclick="cambiarTab('historial')">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarMant" onclick="guardarMantencion()">💾 Guardar</button>
          </div>
        </div>
      </div>
      <div class="drawer-pane" id="pane-dashboard" style="display:none">
        <div id="dashContenedor">
          <div class="drawer-empty"><div class="spinner"></div><p>Cargando...</p></div>
        </div>
      </div>
    </div>
  </div>


  <!-- ══ MODAL CREAR / EDITAR EQUIPO ═════════════════════════════ -->
  <div class="modal-overlay" id="modalForm">
    <div class="modal modal-wide">
      <div class="modal-header">
        <h2 id="modalFormTitulo">Nuevo Equipo</h2>
        <button class="modal-close" onclick="cerrarModal('modalForm')">✕</button>
      </div>

      <!-- Tabs (solo visibles en modo edición) -->
      <div class="modal-tabs" id="modalTabs" style="display:none">
        <div class="modal-tab active" id="mtab-datos"       onclick="cambiarModalTab('datos')">📋 Datos</div>
        <div class="modal-tab"        id="mtab-trazabilidad" onclick="cambiarModalTab('trazabilidad')">📍 Trazabilidad</div>
      </div>

      <!-- Pane: Datos del equipo -->
      <div id="mpane-datos">
        <div class="modal-body">
          <input type="hidden" id="formId">
          <div class="form-grid">
            <div class="form-group">
              <label>Etiqueta</label>
              <input type="text" id="formEtiqueta" placeholder="SBPV1001">
              <span id="formEtiquetaHint" style="display:none;font-size:11px;color:var(--sb-text-light);">Generado automáticamente</span>
            </div>
            <div class="form-group">
              <label>Usuario</label>
              <select id="formUsuario">
                <option value="">Seleccionar usuario...</option>
              </select>
            </div>
            <div class="form-group">
              <label>Marca</label>
              <select id="formMarca">
                <option value="">Seleccionar...</option>
                <option>ACER</option><option>ASUS</option>
                <option>DELL</option><option>HP</option>
                <option>SAMSUNG</option><option>Otro</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha de Compra <span style="color:#a94442">*</span></label>
              <input type="date" id="formAntiguedad">
            </div>
            <div class="form-group full">
              <label>Modelo</label>
              <input type="text" id="formModelo" placeholder="Vivobook X1605ZA">
            </div>
            <div class="form-group full">
              <label>Número de Serie</label>
              <input type="text" id="formSerial" placeholder="SAN0CV05K334420" oninput="limpiarErrorSerial()" onblur="verificarSerial()">
              <span id="formSerialError" style="display:none;font-size:11px;color:#a94442;"></span>
            </div>
            <div class="form-group full">
              <label>Comentario</label>
              <textarea id="formComentario" placeholder="Observaciones adicionales..."></textarea>
            </div>
            <div class="form-group full">
              <label>Ubicación</label>
              <select id="formUbicacion">
                <option value="">Seleccionar...</option>
                <option value="Puerto Varas">Puerto Varas</option>
                <option value="Santiago">Santiago</option>
                <option value="Puerto Natales">Puerto Natales</option>
              </select>
            </div>
            <div class="form-group full">
              <label>Estado</label>
              <select id="formEstado">
                <option value="">Seleccionar...</option>
                <option value="Nuevo">📦 Nuevo</option>
                <option value="Disponible">🟢 Disponible</option>
                <option value="Asignado">🔵 Asignado</option>
                <option value="Servicio técnico">🟡 Servicio técnico</option>
                <option value="Dado de baja">🔴 Dado de baja</option>
              </select>
            </div>
            <!-- Registrado por (solo en edición) -->
            <div class="form-group full" id="wrapRegistradoPor" style="display:none">
              <label>Registrado por <span style="color:#a94442">*</span></label>
              <select id="formRegistradoPor">
                <option value="">Seleccionar responsable...</option>
                <option value="Luis Yagi">Luis Yagi</option>
                <option value="Fabian Velasquez">Fabian Velasquez</option>
                <option value="Juan Felipe Celis">Juan Felipe Celis</option>
              </select>
            </div>
            <!-- Comprobante de compra -->
            <div class="form-group full" id="wrapComprobante">
              <label>Comprobante de compra</label>
              <div class="upload-area">
                <input type="file" id="formComprobante" accept=".pdf,.jpg,.jpeg,.png">
                <span class="upload-hint">PDF, JPG o PNG • máx. 10 MB (opcional)</span>
              </div>
              <div id="comprobanteActualWrap" style="display:none;margin-top:8px;">
                <a id="linkComprobanteActual" href="#" target="_blank" style="color:var(--sb-accent);text-decoration:none;font-size:13px;font-weight:500;">📄 Ver comprobante actual</a>
                <span style="color:var(--sb-text-light);font-size:11px;margin-left:4px;">— o selecciona uno nuevo para reemplazarlo</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="cerrarModal('modalForm')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarEquipo()" id="btnGuardar">💾 Guardar</button>
        </div>
      </div>

      <!-- Pane: Trazabilidad -->
      <div id="mpane-trazabilidad" style="display:none">
        <div class="modal-body" style="min-height:320px;">
          <div id="trazabilidadContenedor">
            <div class="drawer-empty"><div class="spinner"></div><p>Cargando...</p></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="cerrarModal('modalForm')">Cerrar</button>
        </div>
      </div>

    </div>
  </div>

  <!-- ══ MODAL DAR DE BAJA ══════════════════════════════════════ -->
  <div class="modal-overlay" id="modalBaja">
    <div class="modal modal-baja">
      <div class="modal-header modal-header-baja">
        <h2>📴 Dar de baja equipo</h2>
        <button class="modal-close" onclick="cerrarModal('modalBaja')">✕</button>
      </div>
      <div class="modal-body">
        <div class="baja-info-box">
          <div class="baja-info-icon">ℹ️</div>
          <div>
            <strong>El registro no se elimina.</strong> Quedará en el historial con estado "Dado de baja"
            y no aparecerá en las métricas activas del dashboard.
          </div>
        </div>
        <div class="baja-equipo-detalle" id="bajaDetalle"></div>
        <div class="form-group" style="margin-top:16px;">
          <label style="font-weight:600;font-size:13px;">
            Motivo de baja <span style="color:#a94442">*</span>
          </label>
          <select id="bajaMotivo" style="width:100%;margin-top:6px;padding:9px 11px;border-radius:6px;border:1px solid var(--sb-border);font-size:13px;">
            <option value="">Seleccionar motivo...</option>
            <option value="Fin de vida útil">Fin de vida útil</option>
            <option value="Daño irreparable">Daño irreparable</option>
            <option value="Robo / Extravío">Robo / Extravío</option>
            <option value="Reemplazo por nuevo equipo">Reemplazo por nuevo equipo</option>
            <option value="Donación">Donación</option>
            <option value="Otro">Otro</option>
          </select>
          <div id="bajaMotivoOtroWrap" style="display:none;margin-top:8px;">
            <input type="text" id="bajaMotivoOtro" placeholder="Describe el motivo..."
              style="width:100%;padding:9px 11px;border-radius:6px;border:1px solid var(--sb-border);font-size:13px;">
          </div>
          <div id="bajaError" style="color:#a94442;font-size:12px;margin-top:6px;"></div>
        </div>
        <div class="form-group" style="margin-top:14px;">
          <label style="font-weight:600;font-size:13px;">
            Registrado por <span style="color:#a94442">*</span>
          </label>
          <select id="bajaRegistradoPor" style="width:100%;margin-top:6px;padding:9px 11px;border-radius:6px;border:1px solid var(--sb-border);font-size:13px;">
            <option value="">Seleccionar responsable...</option>
            <option value="Luis Yagi">Luis Yagi</option>
            <option value="Fabian Velasquez">Fabian Velasquez</option>
            <option value="Juan Felipe Celis">Juan Felipe Celis</option>
          </select>
          <div id="bajaRegError" style="color:#a94442;font-size:12px;margin-top:4px;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="cerrarModal('modalBaja')">Cancelar</button>
        <button class="btn btn-baja-confirm" id="btnConfirmarBaja" onclick="confirmarBaja()">📴 Confirmar baja</button>
      </div>
    </div>
  </div>

  <!-- ══ MODAL TRAZABILIDAD (desde tabla) ══════════════════════ -->
  <div class="modal-overlay" id="modalTrazabilidad">
    <div class="modal modal-traz">
      <div class="modal-header">
        <div style="flex:1;min-width:0;">
          <h2 id="modalTrazTitulo" style="font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Trazabilidad</h2>
          <p id="modalTrazSub" style="font-size:11px;opacity:0.75;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
        </div>
        <button class="modal-close" onclick="cerrarModal('modalTrazabilidad')">✕</button>
      </div>
      <div class="modal-body">
        <div id="modalTrazContenedor">
          <div class="drawer-empty"><div class="spinner"></div></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="cerrarModal('modalTrazabilidad')">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- ══ MODAL TRACKING USUARIO ═════════════════════════════════ -->
  <div class="modal-overlay" id="modalTrackingUsuario">
    <div class="modal modal-tracking">
      <div class="modal-header">
        <h2>👤 Tracking por usuario</h2>
        <button class="modal-close" onclick="cerrarModal('modalTrackingUsuario')">✕</button>
      </div>
      <div class="modal-body">
        <!-- Selector de usuario -->
        <div class="tracking-search">
          <label style="font-size:12px;font-weight:600;color:var(--sb-text-light);display:block;margin-bottom:6px;">
            Seleccionar usuario
          </label>
          <select id="trackingUsuarioSelect" onchange="cargarTrackingUsuario(this.value)">
            <option value="">— Selecciona un usuario —</option>
          </select>
        </div>
        <!-- Resultado -->
        <div id="trackingResultado">
          <div class="tracking-empty">
            <div style="font-size:32px;margin-bottom:8px;">👤</div>
            <p>Selecciona un usuario para ver su historial de equipos</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="cerrarModal('modalTrackingUsuario')">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- ══ MODAL REPORTE USUARIOS ═════════════════════════════════ -->
  <div class="modal-overlay" id="modalReporteUsuarios">
    <div class="modal modal-reporte">
      <div class="modal-header">
        <h2>📊 Reporte de Usuarios — Equipos Nuevos y Usados</h2>
        <button class="modal-close" onclick="cerrarModal('modalReporteUsuarios')">✕</button>
      </div>
      <div class="modal-body">
        <!-- KPI bar -->
        <div id="reporteKpis" class="reporte-kpi-bar"></div>
        <!-- Filtro -->
        <div class="reporte-filtro">
          <input type="text" id="reporteFiltro" placeholder="🔍 Filtrar por usuario..." oninput="filtrarReporteUsuarios(this.value)">
        </div>
        <!-- Tabla -->
        <div class="reporte-tabla-wrap">
          <table class="reporte-tabla">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Equipo actual</th>
                <th class="col-center">Total equipos</th>
                <th class="col-center">📦 Nuevos</th>
                <th class="col-center">🔄 Usados</th>
              </tr>
            </thead>
            <tbody id="reporteTbody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <div style="display:flex;gap:8px;margin-right:auto;">
          <button class="btn btn-accent btn-sm" onclick="copiarReporteAlPortapapeles()">📋 Copiar tabla</button>
          <button class="btn btn-primary btn-sm" onclick="exportarReporteExcel()">⬇ Exportar Excel</button>
        </div>
        <button class="btn btn-outline" onclick="cerrarModal('modalReporteUsuarios')">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- ══ TOAST ════════════════════════════════════════════════════ -->
  <div class="toast" id="toast"></div>

  <!-- ══ FOOTER ═══════════════════════════════════════════════════ -->
  <div class="footer">
    Inventario Equipos Computacionales &mdash; <?= date('Y') ?>
  </div>

  <script src="assets/js/equipos.js"></script>
</body>
</html>